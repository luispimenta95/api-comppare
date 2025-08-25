<?php

namespace App\Models;

use App\Enums\MeioPagamentoEnum;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Modelo de usuários do sistema
 * 
 * Representa os usuários cadastrados na plataforma com suas informações pessoais,
 * planos de assinatura, pontos, pastas criadas e relacionamentos diversos.
 * Implementa autenticação JWT para APIs.
 */
class Usuarios extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'primeiroNome',
        'sobrenome',
        'apelido',
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
        'subpastasCriadas',
        'pontos',
        'quantidadeConvites',
        'ultimoAcesso',
        'idAssinatura',
        'meioPagamento'
    ];

    protected $hidden = ['senha', 'created_at', 'updated_at'];

    /**
     * Definir casts para conversão automática de tipos
     */
    protected $casts = [
        'meioPagamento' => MeioPagamentoEnum::class,
    ];

    /**
     * Relacionamento: usuário pertence a um plano
     * 
     * Define qual plano de assinatura o usuário possui,
     * determinando suas funcionalidades e limites no sistema.
     * 
     * @return BelongsTo
     */
    public function plano(): BelongsTo
    {
        return $this->belongsTo(Planos::class);
    }

    /**
     * Obtém o identificador único para JWT
     * 
     * @return mixed - Chave primária do usuário
     */
    public function getJWTIdentifier()
    {
        return $this->getKey(); // Retorna a chave primária do usuário
    }

    /**
     * Define claims customizados para o JWT
     * 
     * @return array - Array com dados extras para o payload do JWT
     */
    public function getJWTCustomClaims()
    {
        return []; // Retorne qualquer dado extra que você precise no payload do JWT
    }

    /**
     * Relacionamento: usuário tem muitas transações financeiras
     * 
     * Histórico de todas as transações financeiras realizadas pelo usuário,
     * incluindo pagamentos, reembolsos, etc.
     * 
     * @return HasMany
     */
    public function transacoesFinanceiras(): HasMany
    {
        return $this->hasMany(TransacaoFinanceira::class);
    }

    /**
     * Relacionamento: usuário pode criar muitas tags
     * 
     * Tags personalizadas criadas pelo usuário para organizar suas pastas e fotos.
     * 
     * @return HasMany
     */
    public function tags()
    {
        return $this->hasMany(Tag::class, 'idUsuarioCriador');
    }

    /**
     * Relacionamento: usuário pode ter múltiplas pastas
     * 
     * Pastas que o usuário possui acesso, incluindo as criadas por ele
     * e aquelas compartilhadas por outros usuários.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function pastas()
    {
        return $this->belongsToMany(Pastas::class, 'pasta_usuario', 'usuario_id', 'pasta_id');
    }

    /**
     * Verifica se o usuário usa PIX como meio de pagamento
     * 
     * @return bool
     */
    public function usaPix(): bool
    {
        return $this->meioPagamento === MeioPagamentoEnum::PIX;
    }

    /**
     * Verifica se o usuário usa cartão como meio de pagamento
     * 
     * @return bool
     */
    public function usaCartao(): bool
    {
        return $this->meioPagamento === MeioPagamentoEnum::CARTAO;
    }

    /**
     * Retorna a descrição amigável do meio de pagamento
     * 
     * @return string
     */
    public function getMeioPagamentoDescricao(): string
    {
        return $this->meioPagamento->description();
    }

    /**
     * Retorna o ícone do meio de pagamento
     * 
     * @return string
     */
    public function getMeioPagamentoIcon(): string
    {
        return $this->meioPagamento->icon();
    }
}
