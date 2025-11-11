<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiMermaidService
{
    protected $apiKey;
    protected $model = 'gemini-2.0-flash';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        if (!$this->apiKey) {
            throw new Exception('GEMINI_API_KEY no configurada.');
        }
    }

    /**
     * Convierte JSON de GoJS → Mermaid Class Diagram
     */
    public function toMermaid(array $gojsJson): string
    {
        $prompt = $this->buildPrompt($gojsJson);

        $payload = [
            'contents' => [[
                'parts' => [['text' => $prompt]]
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 2048,
                'response_mime_type' => 'text/plain',
            ],
        ];

        $response = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            $payload
        );

        if ($response->failed()) {
            Log::error('Gemini Mermaid error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new Exception('Error en Gemini: ' . $response->status());
        }

        $mermaidText = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (!$mermaidText) {
            throw new Exception('Gemini no devolvió Mermaid.');
        }

        return $this->cleanMermaid($mermaidText);
    }

    private function buildPrompt(array $gojsJson): string
    {
        $jsonStr = json_encode($gojsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
    Eres un experto en diagramas UML de clases y en la sintaxis Mermaid. Tu tarea es convertir el siguiente JSON de GoJS (GraphLinksModel) en un diagrama de clases válido en formato Mermaid.

    INSTRUCCIONES OBLIGATORIAS:
    1. Usa SOLO el bloque `classDiagram`
    2. Cada clase debe declararse con: `class NombreClase { ... }`
    3. Atributos:
    - `+tipo nombre` → public
    - `-tipo nombre` → private
    - `#tipo nombre` → protected
    - Si no hay visibilidad, asume `+` (public)
    4. Métodos:
    - `+retorno nombre(param1: tipo, param2: tipo)` → public
    - Usa `-` o `#` si aplica
    - Si no hay tipo de retorno, usa `void`
    5. Estereotipos:
    - `<<interface>>` → antes del nombre de la clase
    - `<<abstract>>` → antes del nombre
    6. Relaciones (usa solo estas):
    - **Herencia**: `Padre <|-- Hijo`
    - **Realización**: `Interface <|.. Clase`
    - **Composición**: `Contenedor *-- Parte`
    - **Agregación**: `Contenedor o-- Parte`
    - **Asociación**: `A "card1" -- "card2" B : etiqueta`
    - **Dependencia**: `A ..> B`
    7. Multiplicidad:
    - Usa en asociaciones: `"1"`, `"0..1"`, `"1..*"`, `"0..*"`
    - Si no hay → usa `"1"` por defecto
    8. Etiquetas en relaciones:
    - Usa solo si `fromCardinality` o `toCardinality` o `relationship` lo indican
    - Ej: `: contiene`, `: usa`

    EJEMPLO DE SALIDA ESPERADA:
    classDiagram
        class Producto {
            +String id
            +String nombre
            -float precio
            +void setPrecio(float p)
            +float getPrecio()
        }
        class Stock {
            <<interface>>
            +int cantidad
            +int obtenerDisponible()
        }
        class Venta {
            +Date fecha
        }
        Producto "1" -- "0..*" Stock : contiene
        Venta ..> Producto : vende
        Stock <|.. InventarioFisico

    JSON DE ENTRADA (GoJS):
    {$jsonStr}

    RESPUESTA: SOLO el código Mermaid completo y válido. SIN explicaciones, SIN ```mermaid, SIN texto adicional.
    PROMPT;
    }

    private function cleanMermaid(string $text): string
    {
        // Quitar bloques ```mermaid ``` o ``` 
        return trim(preg_replace('/^```(?:mermaid)?\s*|```$/m', '', $text));
    }
}
