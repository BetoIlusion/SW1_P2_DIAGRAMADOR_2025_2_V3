<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteDiagrama extends Model
{
    protected $table = 'reporte_diagramas';

    protected $fillable = [
        'contenido',
        'ultima_actualizacion',
        'user_id',
        'diagrama_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function diagrama()
    {
        return $this->belongsTo(Diagrama::class);
    }
}
