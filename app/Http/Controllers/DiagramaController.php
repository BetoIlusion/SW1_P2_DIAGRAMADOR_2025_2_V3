<?php

namespace App\Http\Controllers;

use App\Services\GeminiDiagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Diagrama;
use App\Models\UsuarioDiagrama;
use App\Models\ReporteDiagrama;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\GeminiMermaidService;
use App\Services\SpringBootFlutterGeneratorService;
use App\Services\ProjectZipperService;
use App\Services\AIService;


class DiagramaController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Obtener diagramas donde el usuario es creador
        $diagramas = Diagrama::whereHas('usuarioDiagramas', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('tipo_usuario', 'creador')
                ->where('is_active', true);
        })->with(['usuarioDiagramas' => function ($query) {
            $query->where('is_active', true)
                ->with('user');
        }])->get();

        // Transformar los diagramas para incluir colaboradores y no colaboradores
        $diagramas->each(function ($diagrama) use ($user) {
            // Obtener colaboradores actuales (excluyendo al creador)
            $diagrama->colaboradores_actuales = $diagrama->usuarioDiagramas
                ->where('tipo_usuario', 'colaborador')
                ->map(function ($usuarioDiagrama) {
                    return $usuarioDiagrama->user;
                })
                ->filter()
                ->values();

            // Obtener IDs de usuarios que ya son colaboradores o el creador
            $usuariosExcluidos = $diagrama->usuarioDiagramas
                ->pluck('user_id')
                ->toArray();

            // Obtener usuarios no colaboradores (todos los usuarios excepto el actual y los ya relacionados con el diagrama)
            $diagrama->usuarios_no_colaboradores = User::where('id', '!=', $user->id)
                ->whereNotIn('id', $usuariosExcluidos)
                ->get(['id', 'name', 'email'])
                ->values();
        });

        // Obtener todos los usuarios excepto el actual (para referencia general)
        $usuarios = User::where('id', '!=', $user->id)->get(['id', 'name', 'email']);

        return Inertia::render('Principal', [
            'diagramas' => $diagramas,
            'usuarios' => $usuarios,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'El nombre del diagrama es obligatorio.',
            'name.max' => 'El nombre del diagrama no puede exceder los 255 caracteres.',
            'description.string' => 'La descripción debe ser una cadena de texto.',
        ]);
        if (empty($request->description)) {
            $request->merge(['description' => 'sin des']);
        }
        $diagrama = Diagrama::create([
            'nombre' => $request->name,
            'descripcion' => $request->description,
            'is_active' => true,
        ]);

        ReporteDiagrama::create([
            'contenido' => json_encode(Diagrama::diagramaInicial()),
            'ultima_actualizacion' => now(),
            'user_id' => $user->id,
            'diagrama_id' => $diagrama->id,
        ]);

        UsuarioDiagrama::create([
            'tipo_usuario' => 'creador',
            'user_id' => $user->id,
            'diagrama_id' => $diagrama->id,
        ]);
        return redirect()->route('diagrams.show', ['id' => $diagrama->id])->with('success', 'Diagrama creado exitosamente.');
    }
    public function show(string $id)
    {
        $jsonInicial = ReporteDiagrama::obtenerUltimoDiagrama($id);
        return view('diagramador', [
            'jsonInicial' => $jsonInicial,
            'diagramaId' => $id,
        ]);
    }
    public function diagramaReporte(Request $request)
    {
        try {
            $user = Auth::user();
            $validated = $request->validate([
                'diagramData' => 'required',
                'diagramaId' => 'required|exists:diagramas,id'
            ]);

            $diagramaJson = $validated['diagramData'];
            $diagramaId = $validated['diagramaId'];

            if (is_string($diagramaJson)) {
                $diagramaData = json_decode($diagramaJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Error al decodificar JSON: ' . json_last_error_msg());
                }
            } else {
                $diagramaData = $diagramaJson;
            }

            $reporte = ReporteDiagrama::create([
                'contenido' => json_encode($diagramaData),
                'ultima_actualizacion' => now(),
                'user_id' => $user->id,
                'diagrama_id' => $diagramaId,
            ]);
            Log::info('Diagrama guardado correctamente', [
                'user_id' => $user->id,
                'diagrama_id' => $diagramaId,
                'reporte_id' => $reporte->id,
                'contenido_guardado' => $diagramaData // Añadir el contenido guardado al log
            ]);

            // Broadcast el cambio a canal público
            // broadcast(new \App\Events\DiagramaActualizado(
            //     $request->input('diagramaId'),
            //     $request->input('diagramData')
            // ))->toOthers();
            return response()->json([
                'message' => 'Diagrama guardado correctamente',
                'reporte_id' => $reporte->id
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log::warning('Error de validación en diagrama reporte', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Datos inválidos',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function updateWithAI(Request $request)
    {
        try {
            $validated = $request->validate([
                'diagramData' => 'required|string',
                'diagramaId' => 'required|exists:diagramas,id',
                'prompt' => 'required|string|max:500',
            ]);

            $diagramaJson = $validated['diagramData'];
            $userPrompt = $validated['prompt'];
            $diagramaId = $validated['diagramaId'];

            // Usar el servicio de IA
            $aiService = new AIService();
            $updatedDiagramData = $aiService->updateDiagramWithAI($diagramaJson, $userPrompt, $diagramaId);

            // Guardar el nuevo estado en un reporte
            ReporteDiagrama::create([
                'user_id' => Auth::id(), 
                'diagrama_id' => $diagramaId, 
                'contenido' => json_encode($updatedDiagramData)
            ]);

            // Transmitir el cambio a otros usuarios (si es necesario)
            // broadcast(new DiagramaActualizado($diagramaId, json_encode($updatedDiagramData)))->toOthers();

            return response()->json([
                'message' => 'Diagrama actualizado con IA.',
                'updatedDiagram' => $updatedDiagramData
            ]);

        } catch (\Exception $e) {
            Log::error('Error en updateWithAI: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function importImage(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $file = $request->file('file');
        $user = Auth::user();

        try {
            // Instancia del servicio
            $service = new GeminiDiagramService();
            $diagramData = $service->analyzeImage($file->getRealPath(), $file->getMimeType());

            // Crear diagrama
            $diagrama = Diagrama::create([
                'nombre' => 'Diagrama desde Imagen - ' . now()->format('d/m H:i'),
                'descripcion' => 'Generado por IA desde imagen',
                'contenido' => json_encode($diagramData, JSON_PRETTY_PRINT),
            ]);

            // Reporte
            ReporteDiagrama::create([
                'contenido' => json_encode($diagramData),
                'ultima_actualizacion' => now(),
                'user_id' => $user->id,
                'diagrama_id' => $diagrama->id,
            ]);

            // Relación
            UsuarioDiagrama::create([
                'user_id' => $user->id,
                'diagrama_id' => $diagrama->id,
                'tipo_usuario' => 'creador',
                'is_active' => true,
            ]);

            // Redirección directa
            return redirect()
                ->route('diagrams.show', ['id' => $diagrama->id])
                ->with('success', 'Diagrama creado con IA. ¡Listo para editar!');
        } catch (\Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
    public function exportDiagram(string $id)
    {
        try {
            Log::info("Iniciando exportación de diagrama con ID: {$id}");

            // 1. Obtener último JSON del diagrama
            $jsonInicial = ReporteDiagrama::obtenerUltimoDiagrama($id);

            if (!$jsonInicial) {
                Log::warning("Diagrama no encontrado para ID: {$id}");
                return response()->json([
                    'error' => 'No se encontró diagrama con ID: ' . $id
                ], 404);
            }
            Log::info("JSON inicial del diagrama obtenido para ID: {$id}");

            // 2. Convertir a Mermaid
            $mermaidService = new GeminiMermaidService();
            $mermaid = $mermaidService->toMermaid($jsonInicial);
            Log::info("Diagrama convertido a formato Mermaid para ID: {$id}");

            // 3. Generar proyecto Spring Boot + Flutter
            $projectGenerator = new SpringBootFlutterGeneratorService($mermaid, "erp{$id}");
            $result = $projectGenerator->generateCompleteProject();
            // En el método exportDiagram
            Log::info("Generación de proyecto Spring Boot + Frontend completada para ID: {$id}", ['result_success' => $result['success']]);

            if (!$result['success']) {
                Log::error("Error al generar proyecto para ID: {$id}: " . $result['error']);
                return response()->json([
                    'error' => 'Error al generar proyecto: ' . $result['error']
                ], 500);
            }
            Log::info("Proyecto generado exitosamente en: " . $result['project_path']);

            // 4. Crear ZIP del proyecto
            Log::info("Iniciando creación de ZIP...");
            $zipper = new ProjectZipperService();
            $zipPath = $zipper->createZip($result['project_path'], "erp-{$id}.zip");
            Log::info("Archivo ZIP creado exitosamente: {$zipPath}");

            // 5. Verificar que el archivo ZIP existe
            if (!file_exists($zipPath)) {
                Log::error("El archivo ZIP no se creó: {$zipPath}");
                throw new \Exception("No se pudo crear el archivo ZIP");
            }

            Log::info("Enviando archivo ZIP para descarga...");

            Log::info("Archivo ZIP creado exitosamente: {$zipPath}");
            Log::info("Enviando archivo ZIP para descarga...");

            // ✅ SOLUCIÓN: Usar streamDownload para forzar la descarga
            return response()->streamDownload(function () use ($zipPath) {
                echo file_get_contents($zipPath);
            }, "erp-{$id}.zip", [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="erp-' . $id . '.zip"',
            ]);
        } catch (\Exception $e) {
            Log::error("Error inesperado durante la exportación del diagrama ID: {$id}: " . $e->getMessage());
            return response()->json([
                'error' => 'Error en exportación: ' . $e->getMessage()
            ], 500);
        }
    }
}
