<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pastas extends Model
{
    protected $fillable = ['nome', 'idUsuario', 'caminho'];


    public function usuario()
    {
        return $this->belongsTo(Usuarios::class);
    }

}
