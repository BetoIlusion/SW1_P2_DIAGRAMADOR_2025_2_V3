<?php

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
