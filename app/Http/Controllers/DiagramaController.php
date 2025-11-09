<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Diagrama;
use App\Models\UsuarioDiagrama;
use App\Models\ReporteDiagrama;
use App\Models\User;
use Inertia\Inertia;

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
            $query->where('tipo_usuario', 'colaborador')
                ->where('is_active', true)
                ->with('user');
        }])->get();

        // Transformar los diagramas para incluir colaboradores
        $diagramas->each(function ($diagrama) {
            $diagrama->colaboradores_actuales = $diagrama->usuarioDiagramas
                ->map(function ($usuarioDiagrama) {
                    return $usuarioDiagrama->user;
                })
                ->filter(); // Remover valores null
        });

        // Obtener todos los usuarios excepto el actual
        $usuarios = User::where('id', '!=', $user->id)->get();

        return Inertia::render('Principal', [
            'diagramas' => $diagramas,
            'usuarios' => $usuarios,
        ]);
    }
    public function addCollaborator(Request $request)
    {
        $request->validate([
            'diagrama_id' => 'required|exists:diagramas,id',
            'user_id' => 'required|exists:users,id'
        ]);

        // Verificar que el usuario actual es el creador del diagrama
        $diagrama = Diagrama::findOrFail($request->diagrama_id);
        $isCreator = $diagrama->usuarioDiagramas()
            ->where('user_id', Auth::id())
            ->where('tipo_usuario', 'creador')
            ->exists();

        if (!$isCreator) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Crear relación de colaborador
        UsuarioDiagrama::create([
            'tipo_usuario' => 'colaborador',
            'is_active' => true,
            'user_id' => $request->user_id,
            'diagrama_id' => $request->diagrama_id,
        ]);

        return response()->json(['success' => true]);
    }

    public function removeCollaborator(Request $request)
    {
        $request->validate([
            'diagrama_id' => 'required|exists:diagramas,id',
            'user_id' => 'required|exists:users,id'
        ]);

        // Verificar que el usuario actual es el creador del diagrama
        $diagrama = Diagrama::findOrFail($request->diagrama_id);
        $isCreator = $diagrama->usuarioDiagramas()
            ->where('user_id', Auth::id())
            ->where('tipo_usuario', 'creador')
            ->exists();

        if (!$isCreator) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Eliminar relación de colaborador
        UsuarioDiagrama::where('diagrama_id', $request->diagrama_id)
            ->where('user_id', $request->user_id)
            ->where('tipo_usuario', 'colaborador')
            ->delete();

        return response()->json(['success' => true]);
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
        $ultimoReporte = ReporteDiagrama::query()
            ->where('diagrama_id', $id)
            ->latest()->first();
        $jsonInicial = json_decode($ultimoReporte->contenido);
        return view('diagramador', [
            'jsonInicial' => $jsonInicial,
            'diagramaId' => $id,
        ]);
    }
}
