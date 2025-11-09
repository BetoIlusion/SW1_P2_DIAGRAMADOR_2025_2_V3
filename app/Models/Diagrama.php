<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diagrama extends Model
{
    protected $table = 'diagramas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'is_active',
    ];

    public function usuarioDiagramas()
    {
        return $this->hasMany(UsuarioDiagrama::class);
    }

    public static function diagramaInicial(): array
    {
        return [
            "class" => "GraphLinksModel",
            "copiesArrays" => true,
            "copiesArrayObjects" => true,
            "linkCategoryProperty" => "relationship",
            "nodeDataArray" => [
                [
                    "key" => "NewClass",
                    "name" => "NewClass",
                    "properties" => [
                        ["name" => "exampleProperty", "type" => "String", "visibility" => "public"]
                    ],
                    "methods" => [
                        ["name" => "exampleMethod", "parameters" => [["name" => "param", "type" => "int"]], "visibility" => "public"]
                    ]
                ],
                [
                    "key" => "NewClass2",
                    "name" => "NewClass2",
                    "properties" => [],
                    "methods" => []
                ]
            ],
            "linkDataArray" => [
                [
                    "from" => "NewClass",
                    "to" => "NewClass2",
                    "relationship" => "Association Simple",
                    "fromCardinality" => "1..1",
                    "toCardinality" => "1..*"
                ]
            ]
        ];
    }
    
}
