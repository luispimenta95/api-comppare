<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = [
        'nomeTag',              // Nome
        'idUsuarioCriador',  // ID do usuário criador,
        'status'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuarios::class, 'idUsuarioCriador');
    }

    //
}
