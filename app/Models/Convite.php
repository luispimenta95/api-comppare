<?php

namespace App\Models;

class Convite extends BaseModel
{

    protected $fillable = ['email' , 'idUsuario', 'idPasta'];

    public function pastas()
    {
        return $this->belongsTo(Pastas::class, 'idPasta');
    }

}
