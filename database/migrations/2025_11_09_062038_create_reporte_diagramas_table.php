<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reporte_diagramas', function (Blueprint $table) {
            $table->id();
            $table->json('contenido'); // contenido de formato json del diagrama
            $table->timestamp('ultima_actualizacion')->useCurrent(); // fecha de ultima actualizacion
            $table->unsignedBigInteger('user_id'); // id usuario
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('diagrama_id'); // id diagrama
            $table->foreign('diagrama_id')->references('id')->on('diagramas')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporte_diagramas');
    }
};
