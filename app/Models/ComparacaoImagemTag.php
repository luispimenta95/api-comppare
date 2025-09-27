<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparacaoImagemTag extends Model
{
    protected $table = 'comparacoes_imagem_tags';

    protected $fillable = [
        'id_comparacao',
        'id_tag',
        'valor'
    ];

    public function comparacao()
    {
        return $this->belongsTo(ComparacaoImagem::class, 'id_comparacao');
    }
}
