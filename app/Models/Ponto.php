<?php

namespace App\Models;


class Ponto extends BaseModel
{
    protected $fillable = ['idUsuario', 'pontos'];

    public function usuario()
    {
        return $this->belongsTo(Usuarios::class, 'idUsuario');
    }
    //
}
