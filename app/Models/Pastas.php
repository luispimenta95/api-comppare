<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pastas extends Model
{
    protected $fillable = ['nome', 'idUsuario', 'caminho'];


    public function usuario()
    {
        return $this->belongsToMany(Usuarios::class, 'pasta_usuario', 'pasta_id', 'usuario_id');
    }
    public function convite()
    {
        return $this->hasOne(Convite::class);
    }
    public function photos()
    {
        return $this->hasMany(Photos::class);
    }

    /**
     * Relacionamento: uma pasta tem muitas tags com valores associados.
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'folder_tag_values')
            ->withPivot('value')
            ->withTimestamps();
    }
}
