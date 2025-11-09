<?php

use App\Http\Controllers\ColaboradorController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DiagramaController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Route::get('/dashboard', function () {
    //     return Inertia::render('Dashboard');
    // })->name('dashboard');
    Route::get('/dashboard', [DiagramaController::class, 'index'])->name('dashboard');
    Route::prefix('diagrams')->group(function () {
        Route::post('/', [DiagramaController::class, 'store'])->name('diagrams.store');
        Route::get('/{id}', [DiagramaController::class, 'show'])->name('diagrams.show');
        Route::post('/reporte', [DiagramaController::class, 'diagramaReporte'])->name('diagrams.reporte');
        Route::post('/{diagrama}/updateWithAI', [DiagramaController::class, 'updateWithAI'])->name('diagrams.updateWithAI');
    });
    Route::prefix('colaborators')->group(function () {
        Route::get('/', [ColaboradorController::class, 'index'])->name('colaborator');

        Route::post('/add', [ColaboradorController::class, 'addCollaborator'])->name('diagrams.collaborators.add');
        Route::post('/remove', [ColaboradorController::class, 'removeCollaborator'])->name('diagrams.collaborators.remove');
        Route::post('/{diagrama}/leave', [ColaboradorController::class, 'leaveCollaboration'])
            ->name('collaborations.leave');

    });









    // Route::post('/trigger-click', function () {
    //     broadcast(new ClickEvent('Click received!'));
    //     return response()->json(['success' => true]);
    // });

    // Route::get('/clickear', function () {
    //     return Inertia::render('Clickear');
    // })->name('clickear');

    // Route::get('/escuchar', function () {
    //     return Inertia::render('Escuchar');
    // })->name('escuchar');
});
