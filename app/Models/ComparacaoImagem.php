<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparacaoImagem extends Model
{
    protected $table = 'comparacoes_imagem';

    protected $fillable = [
        'id_usuario',
        'id_photo',
        'data_comparacao'
    ];

    public function tags()
    {
        return $this->hasMany(ComparacaoImagemTag::class, 'id_comparacao');
    }
}
