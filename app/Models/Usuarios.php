<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;



class Usuarios extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use Notifiable;

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
        'ultimoAcesso'
    ];

    protected $hidden = ['senha'];

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Planos::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function transacoesFinanceiras(): HasMany
    {
        return $this->hasMany(TransacaoFinanceira::class);
    }

    public function tags()
    {
        return $this->hasMany(Tag::class , 'idUsuarioCriador');
    }
    public function pastas()
    {
        return $this->belongsToMany(Pastas::class, 'pasta_usuario', 'usuario_id', 'pasta_id');
    }
}
