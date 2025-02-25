<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransacaoFinanceira extends Model
{
    use HasFactory;

    protected $fillable = [
        'idPlano',
        'idUsuario',
        'formaPagamento',
        'valorPlano',
        'valorFinalPago',
        'idPedido',
        'pagamentoEfetuado',
        'idUltimoPagamento'
    ];
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class);
    }

    public function planos() :HasMany
    {
        return $this->hasMany(Planos::class);
    }

}
