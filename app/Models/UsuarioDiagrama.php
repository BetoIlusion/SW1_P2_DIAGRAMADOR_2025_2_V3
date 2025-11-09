<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsuarioDiagrama extends Model
{
    protected $table = 'usuario_diagramas';

    protected $fillable = [
        'tipo_usuario',
        'is_active',
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
