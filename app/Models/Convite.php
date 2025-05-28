<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;


class Convite extends Model
{

    protected $fillable = ['email' , 'idUsuario', 'idPasta'];

    public function pastas()
    {
        return $this->belongsTo(Pastas::class, 'idPasta');
    }

}
