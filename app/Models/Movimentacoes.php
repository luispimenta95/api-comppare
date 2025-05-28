<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Movimentacoes extends BaseModel
{
    use HasFactory;

    protected $fillable = [
       'nome_usuario',
        'plano_antigo',
        'plano_novo'
    ];
}
