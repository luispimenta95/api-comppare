<?php

namespace App\Enums;

enum MeioPagamentoEnum: string
{
    case PIX = 'pix';
    case CARTAO = 'cartao';

    /**
     * Retorna todos os valores do enum
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Retorna a descrição amigável do meio de pagamento
     */
    public function description(): string
    {
        return match($this) {
            self::PIX => 'PIX',
            self::CARTAO => 'Cartão de Crédito',
        };
    }

    /**
     * Retorna o ícone do meio de pagamento
     */
    public function icon(): string
    {
        return match($this) {
            self::PIX => '🏦',
            self::CARTAO => '💳',
        };
    }

    /**
     * Verifica se é PIX
     */
    public function isPix(): bool
    {
        return $this === self::PIX;
    }

    /**
     * Verifica se é cartão
     */
    public function isCartao(): bool
    {
        return $this === self::CARTAO;
    }
}
