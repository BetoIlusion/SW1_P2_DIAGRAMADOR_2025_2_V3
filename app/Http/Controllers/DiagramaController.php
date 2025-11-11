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
            // Broadcast el cambio a canal público
            broadcast(new \App\Events\DiagramaActualizado(
                $request->input('diagramaId'),
                $request->input('diagramData')
            ))->toOthers();
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

            // Llamada a la API de Gemini
            $updatedDiagramJson = $this->callGeminiAI($diagramaJson, $userPrompt);

            // Decodificar para validar y guardar
            $updatedDiagramData = json_decode($updatedDiagramJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Log::error('No se pudo decodificar el JSON generado por Gemini.', ['jsonString' => $updatedDiagramJson]);
                throw new \Exception('La respuesta de la IA no es un JSON válido: ' . json_last_error_msg());
            }

            // Verificar estructura mínima de GoJS GraphLinksModel
            if (
                !isset($updatedDiagramData['class']) || $updatedDiagramData['class'] !== 'GraphLinksModel' ||
                !isset($updatedDiagramData['nodeDataArray']) || !isset($updatedDiagramData['linkDataArray'])
            ) {
                // Log::error('El JSON devuelto no cumple con la estructura GoJS GraphLinksModel.', ['jsonString' => $updatedDiagramJson]);
                throw new \Exception('El JSON devuelto no cumple con la estructura GoJS GraphLinksModel.');
            }

            // Guardar el nuevo estado en un reporte
            ReporteDiagrama::create([
                'contenido' => json_encode($updatedDiagramData),
                'ultima_actualizacion' => now(),
                'user_id' => Auth::id(),
                'diagrama_id' => $diagramaId,
            ]);


            // Transmitir el cambio a otros usuarios
            // broadcast(new DiagramaActualizado($diagramaId, $updatedDiagramJson))->toOthers();

            return response()->json([
                'message' => 'Diagrama actualizado con IA.',
                'updatedDiagram' => $updatedDiagramData
            ]);
        } catch (\Exception $e) {
            // Log::error('Error en updateWithAI: ' . $e->getMessage());
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
    public function exportDiagram(string $id) {}
}
