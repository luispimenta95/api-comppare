<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para gerenciamento de planos de assinatura
 * 
 * Define os diferentes planos disponíveis no sistema com suas
 * funcionalidades, limites e valores de cobrança.
 */
class Planos extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'descricao',
        'valor',
        'tempoGratuidade',
        'status',
        'quantidadeTags',
        'quantidadeFotos',
        'quantidadePastas',
        'quantidadeConvites',
        'idHost',
        'frequenciaCobranca',
        'exibicao'
    ];

    /**
     * Relacionamento: um plano pode ter múltiplos usuários
     * 
     * Todos os usuários que possuem este plano de assinatura.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function usuarios()
    {
        return $this->hasMany(Usuarios::class);
    }

    /**
     * Relacionamento: um plano tem múltiplas funcionalidades
     * 
     * Define quais funcionalidades estão disponíveis para este plano.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function funcionalidades()
    {
        return $this->belongsToMany(Funcionalidades::class);
    }

    /**
     * Relacionamento: um plano pertence a transações financeiras
     * 
     * Histórico de transações relacionadas a este plano.
     * 
     * @return BelongsTo
     */
    public function transacoesFinanceiras(): BelongsTo
    {
        return $this->belongsTo(TransacaoFinanceira::class);
    }
}
