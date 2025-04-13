<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = [
        'nome',              // Nome
        'descricao',         // Campo adicional: Descrição
        'idUsuarioCriador',  // ID do usuário criador,
        'status'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuarios::class, 'idUsuarioCriador');
    }

    //
}
