<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ponto extends Model
{
    protected $fillable = ['acao', 'idUsuario', 'pontos'];

    public function usuario()
    {
        return $this->belongsTo(Usuarios::class, 'idUsuario');
    }
    //
}
