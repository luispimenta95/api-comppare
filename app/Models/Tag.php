<?php

namespace App\Models;


class Tag extends BaseModel
{
    protected $fillable = [
        'label',              // Nome
        'valor',         // Campo adicional: Descrição
        'idUsuarioCriador',  // ID do usuário criador,
        'status'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuarios::class, 'idUsuarioCriador');
    }

    //
}
