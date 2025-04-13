<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Usuarios extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nome',
        'senha',
        'email',
        'cpf',
        'status',
        'dataLimiteCompra',
        'dataNascimento',
        'telefone',
        'dataUltimoPagamento',
        'idUltimaCobranca',
        'idPlano',
        'idPerfil',
        'pastasCriadas',
        'pontos',
        'quantidadeConvites',
        'ultimoAcesso'
    ];

    protected $hidden = ['senha'];

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Planos::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey(); // Retorna a chave primária do usuário
    }

    public function getJWTCustomClaims()
    {
        return []; // Retorne qualquer dado extra que você precise no payload do JWT
    }

    public function transacoesFinanceiras(): HasMany
    {
        return $this->hasMany(TransacaoFinanceira::class);
    }

    public function tags()
    {
        return $this->hasMany(Tag::class, 'idUsuarioCriador');
    }

    public function pastas()
    {
        return $this->belongsToMany(Pastas::class, 'pasta_usuario', 'usuario_id', 'pasta_id');
    }
}
