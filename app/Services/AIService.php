<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AIService
{
    public function updateDiagramWithAI(string $diagramaJson, string $userPrompt, int $diagramaId): array
    {
        try {
            // Llamada a la API de Gemini
            $updatedDiagramJson = $this->callGeminiAI($diagramaJson, $userPrompt);

            // Decodificar para validar y guardar
            $updatedDiagramData = json_decode($updatedDiagramJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('No se pudo decodificar el JSON generado por Gemini.', ['jsonString' => $updatedDiagramJson]);
                throw new Exception('La respuesta de la IA no es un JSON válido: ' . json_last_error_msg());
            }

            // Normalizar los datos
            $updatedDiagramData = $this->normalizeDiagramData($updatedDiagramData);

            // Verificar estructura mínima de GoJS GraphLinksModel
            if (
                !isset($updatedDiagramData['class']) || $updatedDiagramData['class'] !== 'GraphLinksModel' ||
                !isset($updatedDiagramData['nodeDataArray']) || !isset($updatedDiagramData['linkDataArray'])
            ) {
                Log::error('El JSON devuelto no cumple con la estructura GoJS GraphLinksModel.', ['jsonString' => $updatedDiagramJson]);
                throw new Exception('El JSON devuelto no cumple con la estructura GoJS GraphLinksModel.');
            }

            // Validar estructura UML
            if (!$this->validateUMLStructure($updatedDiagramData)) {
                Log::error('El JSON devuelto no cumple con la estructura UML requerida.', [
                    'jsonString' => json_encode($updatedDiagramData)
                ]);
                throw new Exception('El JSON devuelto no cumple con la estructura UML requerida.');
            }

            return $updatedDiagramData;

        } catch (Exception $e) {
            Log::error('Error en AIService: ' . $e->getMessage());
            throw $e;
        }
    }

    private function callGeminiAI(string $diagramaJson, string $userPrompt): string
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            Log::error('La clave API de Gemini no está configurada en el archivo .env.');
            throw new Exception('Clave API de Gemini no configurada en .env');
        }

        $jsonEjemplo = json_encode([
            "class" => "GraphLinksModel",
            "copiesArrays" => true,
            "copiesArrayObjects" => true,
            "linkCategoryProperty" => "relationship",
            "nodeDataArray" => [
                [
                    "key" => "Usuario",
                    "name" => "Usuario",
                    "properties" => [
                        ["name" => "id", "type" => "int", "visibility" => "private"],
                        ["name" => "nombre", "type" => "String", "visibility" => "public"]
                    ],
                    "methods" => [
                        ["name" => "login", "parameters" => [], "visibility" => "public", "type" => "boolean"]
                    ]
                ]
            ],
            "linkDataArray" => [
                [
                    "from" => "Usuario",
                    "to" => "Rol",
                    "relationship" => "Association",
                    "multiplicityFrom" => "1",
                    "multiplicityTo" => "0..*"
                ]
            ]
        ], JSON_PRETTY_PRINT);

        $promptText = <<<EOT
            Eres un experto en ingeniería de software y UML. Tu tarea es actualizar el JSON de un diagrama de clases UML en formato GoJS GraphLinksModel según las instrucciones proporcionadas.

            EJEMPLO DE JSON VÁLIDO:
            {$jsonEjemplo}

            JSON ACTUAL DEL DIAGRAMA:
            {$diagramaJson}

            INSTRUCCIONES DEL USUARIO:
            {$userPrompt}

            CONCEPTOS UML QUE DEBES CONSIDERAR:

            1. 🔷 ESTEREOTIPOS (Stereotypes):
            - Para clases: "abstract", "interface", "enum", "utility", etc.
            - Para relaciones: "include", "extend", etc.
            - Ejemplo: {"name": "Usuario", "stereotype": "abstract"}

            2. 🔗 RELACIONES VÁLIDAS (USAR EXACTAMENTE):
            - "Association" (para asociaciones simples y normales)
            - "Inheritance" (para herencia/generalización)
            - "Realization" (para realización de interfaces)
            - "Dependency" (para dependencias)
            - "Composition" (para composición)
            - "Aggregation" (para agregación)

            3. 📊 CARDINALIDADES (Multiplicidades - USAR multiplicityFrom y multiplicityTo):
            - "1" (exactamente uno)
            - "0..1" (cero o uno)
            - "0..*" o "*" (cero o muchos)
            - "1..*" (uno o muchos)
            - "n..m" (rango específico)
            - Ejemplo: {"multiplicityFrom": "1", "multiplicityTo": "0..*"}

            4. 🏗️ CLASES INTERMEDIAS/ASOCIATIVAS:
            - Para relaciones muchos-a-muchos
            - Deben tener propiedades que representen los atributos de la relación
            - Conectadas con asociaciones a ambas clases

            5. 🎯 ESTRUCTURA DE NODOS (Clases):
            - "key": Identificador único (usar nombre de clase si es único)
            - "name": Nombre de la clase
            - "stereotype": Estereotipo opcional
            - "properties": Array de atributos
                - "name": Nombre del atributo
                - "type": Tipo de dato
                - "visibility": "public", "private", "protected"
                - "default": Valor por defecto (opcional)
            - "methods": Array de métodos
                - "name": Nombre del método
                - "parameters": Array de parámetros [{"name": "param", "type": "Tipo"}]
                - "visibility": "public", "private", "protected" 
                - "type": Tipo de retorno

            6. 🔗 ESTRUCTURA DE ENLACES (Relaciones) - FORMATO GOJS:
            - "from": key de la clase origen
            - "to": key de la clase destino  
            - "relationship": Tipo de relación (USAR LOS 6 TIPOS VÁLIDOS)
            - "multiplicityFrom": Cardinalidad en origen
            - "multiplicityTo": Cardinalidad en destino
            - "stereotype": Estereotipo de relación (opcional)

            EJEMPLOS CORRECTOS DE RELACIONES:
            {
            "from": "Usuario",
            "to": "Rol", 
            "relationship": "Association",
            "multiplicityFrom": "1",
            "multiplicityTo": "0..*"
            }

            {
            "from": "Estudiante",
            "to": "Persona",
            "relationship": "Inheritance"
            }

            {
            "from": "Cliente",
            "to": "Servicio",
            "relationship": "Dependency"
            }

            INSTRUCCIONES ESTRICTAS:
            - Devuelve ÚNICAMENTE el JSON actualizado en formato GoJS GraphLinksModel
            - MANTÉN la estructura existente cuando no haya cambios
            - Para HERENCIA usa "relationship": "Inheritance" 
            - Para INTERFACES usa "stereotype": "interface"
            - Para CLASES ABSTRACTAS usa "stereotype": "abstract"
            - USA "multiplicityFrom" y "multiplicityTo" para las multiplicidades
            - USA SOLO los 6 tipos de relación válidos: Association, Inheritance, Realization, Dependency, Composition, Aggregation
            - CREA clases intermedias para relaciones muchos-a-muchos
            - CONSERVA todas las clases y relaciones existentes a menos que se indique lo contrario

            FORMATO DE SALIDA EXACTO:
            {
            "class": "GraphLinksModel",
            "copiesArrays": true,
            "copiesArrayObjects": true,
            "linkCategoryProperty": "relationship",
            "nodeDataArray": [...],
            "linkDataArray": [...]
            }

            NO incluyas explicaciones, comentarios ni texto fuera del JSON.
            EOT;

        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptText]
                    ]
                ]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature'        => 0.3,
                'topK'               => 40,
                'topP'               => 0.9,
                'maxOutputTokens'    => 4096,
                'stopSequences'      => []
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT',       'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',      'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE']
            ]
        ];

        Log::info("Enviando solicitud a Google Gemini API para actualización UML");
        Log::debug('Payload length: ' . strlen($promptText));

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(90)->post($apiUrl, $payload);

        Log::info('Respuesta recibida de Google Gemini API.', ['status_code' => $response->status()]);

        if ($response->failed()) {
            Log::error('Error en la llamada a la API de Google Gemini.', [
                'status' => $response->status(),
                'body'   => $response->body()
            ]);
            throw new Exception('Error en la respuesta de Gemini: Status ' . $response->status() . ' - ' . $response->body());
        }

        $data = $response->json();
        Log::debug('Respuesta JSON de Google Gemini decodificada.');

        $jsonString = data_get($data, 'candidates.0.content.parts.0.text');
        if (is_null($jsonString)) {
            $finishReason  = data_get($data, 'candidates.0.finishReason', 'N/A');
            $safetyRatings = json_encode(data_get($data, 'candidates.0.safetyRatings', []));

            Log::warning('No se encontró texto en la respuesta de Gemini o fue bloqueada.', [
                'finishReason'  => $finishReason,
                'safetyRatings' => $safetyRatings
            ]);

            if ($finishReason === 'SAFETY') {
                throw new Exception('La respuesta fue bloqueada por razones de seguridad.');
            } elseif ($finishReason === 'RECITATION') {
                throw new Exception('La respuesta fue bloqueada por razones de citación.');
            } elseif (empty(data_get($data, 'candidates'))) {
                $promptFeedback = json_encode(data_get($data, 'promptFeedback', 'N/A'));
                Log::error('La solicitud fue probablemente bloqueada antes de generar candidatos.', [
                    'promptFeedback' => $promptFeedback
                ]);
                throw new Exception('La solicitud fue bloqueada (posiblemente por seguridad del prompt).');
            } else {
                throw new Exception('No se pudo obtener una respuesta válida de Gemini.');
            }
        }

        // Limpiar el string
        $jsonString = preg_replace('/^```json|```$/m', '', $jsonString);
        $jsonString = trim($jsonString);
        $jsonString = preg_replace('/\s+/', ' ', $jsonString);

        // Validar el JSON
        $jsonDecoded = json_decode($jsonString, true);
        if (is_null($jsonDecoded)) {
            Log::error('No se pudo decodificar el JSON generado por Gemini.', ['jsonString' => $jsonString]);
            throw new Exception('No se pudo decodificar el JSON generado por Gemini: ' . json_last_error_msg());
        }

        Log::info('JSON UML generado por Gemini validado correctamente.');
        return json_encode($jsonDecoded);
    }

    private function normalizeDiagramData(array $diagramData): array
    {
        // Normalizar cardinalidades en los enlaces
        if (isset($diagramData['linkDataArray']) && is_array($diagramData['linkDataArray'])) {
            foreach ($diagramData['linkDataArray'] as &$link) {
                // Si la IA generó fromCardinality/toCardinality, convertirlos a multiplicityFrom/multiplicityTo
                if (isset($link['fromCardinality']) && !isset($link['multiplicityFrom'])) {
                    $link['multiplicityFrom'] = $link['fromCardinality'];
                    unset($link['fromCardinality']);
                }
                if (isset($link['toCardinality']) && !isset($link['multiplicityTo'])) {
                    $link['multiplicityTo'] = $link['toCardinality'];
                    unset($link['toCardinality']);
                }

                // Normalizar tipos de relación
                if (isset($link['relationship'])) {
                    $link['relationship'] = $this->normalizeRelationshipType($link['relationship']);
                }
            }
        }
        
        return $diagramData;
    }

    private function normalizeRelationshipType(string $relationship): string
    {
        $normalized = strtolower(trim($relationship));
        
        $mapping = [
            'association simple' => 'Association',
            'association' => 'Association',
            'associationsimple' => 'Association',
            'inheritance' => 'Inheritance',
            'realization' => 'Realization',
            'dependency' => 'Dependency',
            'composition' => 'Composition',
            'aggregation' => 'Aggregation'
        ];

        return $mapping[$normalized] ?? $relationship;
    }

    private function validateUMLStructure(array $diagramData): bool
    {
        Log::info('Iniciando validación UML', [
            'nodeCount' => count($diagramData['nodeDataArray']), 
            'linkCount' => count($diagramData['linkDataArray'])
        ]);

        // Validación básica de GraphLinksModel
        if (!isset($diagramData['class']) || $diagramData['class'] !== 'GraphLinksModel') {
            Log::error('Falta class o no es GraphLinksModel');
            return false;
        }

        if (!isset($diagramData['nodeDataArray']) || !is_array($diagramData['nodeDataArray'])) {
            Log::error('Falta nodeDataArray o no es array');
            return false;
        }

        if (!isset($diagramData['linkDataArray']) || !is_array($diagramData['linkDataArray'])) {
            Log::error('Falta linkDataArray o no es array');
            return false;
        }

        // Validar nodos (clases)
        foreach ($diagramData['nodeDataArray'] as $index => $node) {
            if (!isset($node['key']) || !isset($node['name'])) {
                Log::error('Nodo sin key o name en índice: ' . $index, ['node' => $node]);
                return false;
            }

            // Validar propiedades si existen
            if (isset($node['properties']) && is_array($node['properties'])) {
                foreach ($node['properties'] as $propIndex => $prop) {
                    if (!isset($prop['name']) || !isset($prop['type'])) {
                        Log::error('Propiedad sin name o type en nodo: ' . $node['key'] . ' en propiedad índice: ' . $propIndex);
                        return false;
                    }
                }
            }

            // Validar métodos si existen
            if (isset($node['methods']) && is_array($node['methods'])) {
                foreach ($node['methods'] as $methodIndex => $method) {
                    if (!isset($method['name'])) {
                        Log::error('Método sin name en nodo: ' . $node['key'] . ' en método índice: ' . $methodIndex);
                        return false;
                    }
                }
            }
        }

        // Validar enlaces (relaciones)
        foreach ($diagramData['linkDataArray'] as $index => $link) {
            if (!isset($link['from']) || !isset($link['to']) || !isset($link['relationship'])) {
                Log::error('Enlace sin from, to o relationship en índice: ' . $index, ['link' => $link]);
                return false;
            }

            // Validar que las claves de from y to existen en nodeDataArray
            $fromExists = false;
            $toExists = false;
            foreach ($diagramData['nodeDataArray'] as $node) {
                if ($node['key'] == $link['from']) $fromExists = true;
                if ($node['key'] == $link['to']) $toExists = true;
            }

            if (!$fromExists || !$toExists) {
                Log::error('Enlace con from o to que no existe en nodeDataArray: ' . $link['from'] . ' -> ' . $link['to']);
                return false;
            }

            // Validar tipos de relación válidos (con normalización)
            $validRelationships = ['Association', 'Inheritance', 'Realization', 'Dependency', 'Composition', 'Aggregation'];
            $normalizedRelationship = $this->normalizeRelationshipType($link['relationship']);
            
            if (!in_array($normalizedRelationship, $validRelationships)) {
                Log::error('Relación no válida: ' . $link['relationship'] . ' (normalizada: ' . $normalizedRelationship . ') en enlace: ' . $index);
                return false;
            }

            // Actualizar la relación normalizada
            $link['relationship'] = $normalizedRelationship;
        }

        Log::info('Validación UML exitosa');
        return true;
    }
}