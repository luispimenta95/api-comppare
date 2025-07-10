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
        return $this->hasMany(Photos::class, 'pasta_id');
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

    public function subpastas()
    {
        return $this->hasMany(Pastas::class, 'pasta_pai_id');
    }

    // Relacionamento inverso com a pasta pai
    public function pastaPai()
    {
        return $this->belongsTo(Pastas::class, 'pasta_pai_id');
    }
}
