<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagamentoPix extends Model
{
    use HasFactory;

    protected $table = 'pagamento_pix';

    protected $fillable = [
        'idUsuario',
        'txid',
        'numeroContrato',
        'pixCopiaECola',
        'valor',
        'chavePixRecebedor',
        'nomeDevedor',
        'cpfDevedor',
        'locationId',
        'recId',
        'status',
        'statusPagamento',
        'dataInicial',
        'dataFinal',
        'periodicidade',
        'objeto',
        'dataVencimento',
        'dataPagamento',
        'responseApiCompleta'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'dataInicial' => 'date',
        'dataFinal' => 'date',
        'dataVencimento' => 'datetime',
        'dataPagamento' => 'datetime',
        'responseApiCompleta' => 'array'
    ];

    /**
     * Relacionamento com usuário
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'idUsuario');
    }

    /**
     * Scopes para facilitar consultas
     */
    public function scopeAtivo($query)
    {
        return $query->where('status', 'ATIVA');
    }

    public function scopePendente($query)
    {
        return $query->where('statusPagamento', 'PENDENTE');
    }

    public function scopePago($query)
    {
        return $query->where('statusPagamento', 'PAGO');
    }

    public function scopePorUsuario($query, $idUsuario)
    {
        return $query->where('idUsuario', $idUsuario);
    }

    /**
     * Método para marcar como pago
     */
    public function marcarComoPago(): void
    {
        $this->update([
            'statusPagamento' => 'PAGO',
            'dataPagamento' => now()
        ]);
    }

    /**
     * Método para cancelar pagamento
     */
    public function cancelar(): void
    {
        $this->update([
            'statusPagamento' => 'CANCELADO',
            'status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR'
        ]);
    }
}
