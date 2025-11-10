<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Diagrama;
use App\Models\ReporteDiagrama;
use App\Models\UsuarioDiagrama;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ColaboradorController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        try {
            $diagramas = Diagrama::whereHas('usuarioDiagramas', function ($query) use ($user) {
                $query->where('user_id', $user->id)->where('tipo_usuario', 'colaborador');
            })
                ->with(['usuarioDiagramas.user'])
                ->get()
                ->map(function ($diagrama) {
                    // 🔥 VERIFICACIÓN SEGURA MEJORADA
                    $creador = $diagrama->usuarioDiagramas
                        ->where('tipo_usuario', 'creador')
                        ->first();

                    $creatorName = 'Unknown Creator';

                    if ($creador && $creador->user) {
                        $creatorName = $creador->user->name;
                    } else {
                        // Si no encuentra creador, buscar cualquier usuario relacionado
                        $anyUser = $diagrama->usuarioDiagramas->first();
                        if ($anyUser && $anyUser->user) {
                            $creatorName = $anyUser->user->name;
                        }
                    }

                    return [
                        'id' => $diagrama->id,
                        'nombre' => $diagrama->nombre,
                        'descripcion' => $diagrama->descripcion,
                        'is_active' => $diagrama->is_active,
                        'created_at' => $diagrama->created_at,
                        'updated_at' => $diagrama->updated_at,
                        'creator_name' => $creatorName,
                        'usuario_diagramas' => $diagrama->usuarioDiagramas->map(function ($ud) {
                            return [
                                'user_id' => $ud->user_id,
                                'tipo_usuario' => $ud->tipo_usuario,
                                'user' => $ud->user ? [
                                    'id' => $ud->user->id,
                                    'name' => $ud->user->name,
                                    'email' => $ud->user->email,
                                ] : null
                            ];
                        })->filter()->values() // 🔥 AÑADIR values() para asegurar array
                    ];
                });

            return Inertia::render('Colaborador/Index', [
                'diagramas' => $diagramas,
                'auth_user' => Auth::user(), // ← AÑADE ESTO
            ]);
        } catch (\Exception $e) {

            return Inertia::render('Colaborador/Index', [
                'diagramas' => [],
                'error' => 'Error loading collaborations'
            ]);
        }
    }
    public function addCollaborator(Request $request)
    {
        $request->validate([
            'diagrama_id' => 'required|exists:diagramas,id',
            'user_id' => 'required|exists:users,id'
        ]);

        try {
            // Verificar que el usuario actual es el creador del diagrama
            $diagrama = Diagrama::findOrFail($request->diagrama_id);
            $isCreator = $diagrama->usuarioDiagramas()
                ->where('user_id', Auth::id())
                ->where('tipo_usuario', 'creador')
                ->exists();

            if (!$isCreator) {
                return back()->with('error', 'Unauthorized to add collaborators to this diagram.');
            }

            // Verificar si el usuario ya es colaborador
            $existingCollaborator = UsuarioDiagrama::where('diagrama_id', $request->diagrama_id)
                ->where('user_id', $request->user_id)
                ->where('tipo_usuario', 'colaborador')
                ->exists();

            if ($existingCollaborator) {
                return back()->with('error', 'User is already a collaborator of this diagram.');
            }

            // Crear relación de colaborador
            UsuarioDiagrama::create([
                'tipo_usuario' => 'colaborador',
                'is_active' => true,
                'user_id' => $request->user_id,
                'diagrama_id' => $request->diagrama_id,
            ]);
            Log::info('COLLABORATOR_ADDED', [
                'diagrama_id' => $request->diagrama_id,
                'user_id' => $request->user_id,
                'action' => 'added',
                'broadcast_to' => [
                    'diagrama.' . $request->diagrama_id,
                    'user.' . $request->user_id
                ]
            ]);
            // Broadcast event para notificar al usuario agregado
            broadcast(new \App\Events\CollaboratorUpdated($request->diagrama_id, $request->user_id, 'added'));
            return back()->with('success', 'Collaborator added successfully!');
        } catch (\Exception $e) {
            Log::error('Error adding collaborator: ' . $e->getMessage());
            return back()->with('error', 'Error adding collaborator: ' . $e->getMessage());
        }
    }

    public function removeCollaborator(Request $request)
    {
        $request->validate([
            'diagrama_id' => 'required|exists:diagramas,id',
            'user_id' => 'required|exists:users,id'
        ]);

        try {
            // Verificar que el usuario actual es el creador del diagrama
            $diagrama = Diagrama::findOrFail($request->diagrama_id);
            $isCreator = $diagrama->usuarioDiagramas()
                ->where('user_id', Auth::id())
                ->where('tipo_usuario', 'creador')
                ->exists();

            if (!$isCreator) {
                return back()->with('error', 'Unauthorized to remove collaborators from this diagram.');
            }

            // Eliminar relación de colaborador
            $deleted = UsuarioDiagrama::where('diagrama_id', $request->diagrama_id)
                ->where('user_id', $request->user_id)
                ->where('tipo_usuario', 'colaborador')
                ->delete();

            if ($deleted) {
                // Broadcast event para notificar al usuario removido
                broadcast(new \App\Events\CollaboratorUpdated($request->diagrama_id, $request->user_id, 'removed'));

                return back()->with('success', 'Collaborator removed successfully!');
            }
            Log::info('COLLABORATOR_ADDED', [
                'diagrama_id' => $request->diagrama_id,
                'user_id' => $request->user_id,
                'action' => 'removed',
                'broadcast_to' => [
                    'diagrama.' . $request->diagrama_id,
                    'user.' . $request->user_id
                ]
            ]);
            return back()->with('error', 'Collaborator not found.');
        } catch (\Exception $e) {
            \Log::error('Error removing collaborator: ' . $e->getMessage());
            return back()->with('error', 'Error removing collaborator: ' . $e->getMessage());
        }
    }
    public function leaveCollaboration(Diagrama $diagrama)
    {
        $user = Auth::user();

        // Eliminar la relación de colaboración
        $diagrama->usuarioDiagramas()
            ->where('user_id', $user->id)
            ->where('tipo_usuario', 'colaborador')
            ->delete();

        return redirect()->route('colaborator')
            ->with('success', 'You have left the collaboration successfully.');
    }
}
