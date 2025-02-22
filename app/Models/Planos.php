<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Planos extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'descricao', 'valor', 'tempoGratuidade', 'status','quantidadeTags'];

    public function usuarios()
    {
        return $this->hasMany(Usuarios::class);
    }

    public function funcionalidades()
    {
        return $this->belongsToMany(Funcionalidades::class);
    }

    public function transacoesFinanceiras(): BelongsTo
    {
        return $this->belongsTo(TransacaoFinanceira::class);
    }
}
