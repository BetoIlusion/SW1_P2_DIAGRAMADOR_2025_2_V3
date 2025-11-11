<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiDiagramService
{
    protected $apiKey;
    protected $model = 'gemini-2.0-flash';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        if (!$this->apiKey) {
            throw new Exception('GEMINI_API_KEY no configurada en .env');
        }
    }

    /**
     * Procesa imagen y retorna JSON válido de GoJS
     */
    public function analyzeImage($filePath, $mimeType)
    {
        $imageBase64 = base64_encode(file_get_contents($filePath));
        $prompt = $this->getUMLPrompt();

        $payload = $this->buildPayload($prompt, $imageBase64, $mimeType);

        $response = Http::timeout(120)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            $payload
        );

        if ($response->failed()) {
            Log::error('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new Exception('Error en Gemini API: ' . $response->status());
        }

        $jsonString = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (!$jsonString) {
            throw new Exception('Gemini no devolvió JSON.');
        }

        $cleanJson = $this->cleanJson($jsonString);
        $diagramData = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON inválido de Gemini: ' . json_last_error_msg());
        }

        if (!$this->validateStructure($diagramData)) {
            throw new Exception('Estructura de diagrama inválida.');
        }

        return $diagramData;
    }

    private function getUMLPrompt(): string
    {
        return <<<PROMPT
        Eres un experto en análisis de diagramas UML de clases. Analiza la imagen del diagrama y genera un JSON en el formato EXACTO requerido por GoJS GraphLinksModel.

        FORMATO DE SALIDA REQUERIDO (JSON):
        {
        "class": "GraphLinksModel",
        "copiesArrays": true,
        "copiesArrayObjects": true,
        "linkCategoryProperty": "relationship",
        "nodeDataArray": [
            {
            "key": "NombreClase1",
            "name": "NombreClase1",
            "stereotype": "", // Opcional: "interface", "abstract", etc.
            "properties": [
                {
                "name": "nombreAtributo",
                "type": "Tipo",
                "visibility": "public", // "public", "private", "protected"
                "default": "" // Valor por defecto opcional
                }
            ],
            "methods": [
                {
                "name": "nombreMetodo",
                "parameters": [
                    {
                    "name": "paramNombre",
                    "type": "TipoParam"
                    }
                ],
                "visibility": "public",
                "type": "TipoRetorno" // String, void, int, etc.
                }
            ]
            }
        ],
        "linkDataArray": [
            {
            "from": "ClaseOrigen",
            "to": "ClaseDestino",
            "relationship": "Association", // "Association", "Inheritance", "Composition", "Aggregation", "Dependency", "Realization"
            "multiplicityFrom": "1", // "1", "0..1", "0..*", "1..*", etc.
            "multiplicityTo": "0..*",
            "stereotype": "" // Opcional para relaciones
            }
        ]
        }

        INSTRUCCIONES CRÍTICAS:
        1. Analiza TODOS los elementos visibles en el diagrama UML
        2. Para cada clase, identifica: nombre, atributos (nombre, tipo, visibilidad) y métodos (nombre, parámetros, tipo retorno, visibilidad)
        3. Para relaciones: identifica tipo (Asociación, Herencia, Composición, Agregación, Dependencia, Realización) y multiplicidades
        4. Detecta estereotipos: <<interface>>, <<abstract>>, etc.
        5. Usa claves únicas (key) simples basadas en nombres de clase
        6. Si hay clases asociativas/intermedias, créalas como nodos normales
        7. Para herencia, usa relationship: "Inheritance"
        8. Para interfaces, usa stereotype: "interface" en el nodo

        EJEMPLO DE RELACIONES:
        - Herencia: {"from": "Hijo", "to": "Padre", "relationship": "Inheritance"}
        - Asociación simple: {"from": "ClaseA", "to": "ClaseB", "relationship": "Association", "multiplicityFrom": "1", "multiplicityTo": "1"}
        - Composición: {"from": "Contenedor", "to": "Contenido", "relationship": "Composition"}
        - Agregación: {"from": "Contenedor", "to": "Contenido", "relationship": "Aggregation"}
        - Dependencia: {"from": "Cliente", "to": "Servicio", "relationship": "Dependency"}
        - Realización: {"from": "Implementacion", "to": "Interfaz", "relationship": "Realization"}

        RESPONDE ÚNICAMENTE con el JSON válido, sin explicaciones adicionales.
        PROMPT;
    }

    private function buildPayload($prompt, $base64, $mimeType): array
    {
        return [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64]]
                ]
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 4096,
                'response_mime_type' => 'application/json',
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ]
        ];
    }

    private function cleanJson(string $json): string
    {
        return trim(preg_replace('/^```json|```$/m', '', $json));
    }

    private function validateStructure(array $data): bool
    {
        if (!isset($data['class']) || $data['class'] !== 'GraphLinksModel') return false;
        if (!is_array($data['nodeDataArray'] ?? null)) return false;
        if (!is_array($data['linkDataArray'] ?? null)) return false;

        foreach ($data['nodeDataArray'] as $node) {
            if (!isset($node['key'], $node['name'])) return false;
        }

        foreach ($data['linkDataArray'] as $link) {
            if (!isset($link['from'], $link['to'], $link['relationship'])) return false;
        }

        return true;
    }
}