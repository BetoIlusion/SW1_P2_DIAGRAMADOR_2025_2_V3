<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Diagrama;
use App\Models\User;
use Inertia\Inertia;


class DiagramaController extends Controller
{
     public function index()
    {
        $user = Auth::user();
        $diagramas = Diagrama::whereHas('usuarioDiagramas', function ($query) use ($user) {
            $query->where('user_id', $user->id)->where('tipo_usuario', 'creador');
        })->get();
        //         {
        //     "id": 1,
        //     "nombre": "diagram000",
        //     "descripcion": "sin descripcion",
        //     "is_active": 1,
        //   },
        $usuarios = User::where('id', '!=', $user->id)->get();
        // $usuarios = [
        //     {
        //         "id": 1,
        //         "name": "Test User",
        //         "email": "test@example.com",
        //     }
        // ]

        return Inertia::render('Principal', [
            'diagramas' => $diagramas, 
            'usuarios' => $usuarios
        ]);
    }
}
