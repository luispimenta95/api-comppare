<?php

namespace App\Http\Util;

use App\Mail\EmailPagamento;
use App\Mail\EmailWelcome;
use Illuminate\Support\Facades\Mail;

class MailHelper


{
    /**
     * Retorna todos os códigos HTTP e suas descrições.
     *
     * @return array
     */
    public static function boasVindas(array $dados, string $mailTo): void //testing webhocks
    {
        $dadosEmail = [
            'to' => $mailTo,
            'body' => [
                'nome' => $dados['nome'],
                'nomePlano' => $dados['nomePlano']
            ],
        ];


        Mail::to($mailTo)->send(new EmailWelcome($dadosEmail));
    }

    public static function confirmacaoPagamento(array $dados, string $mailTo): void //testing webhocks
    {
        $dadosEmail = [
            'to' => $mailTo,
            'body' => [
                'nome' => $dados['nome'],
                'dataRenovacao' => $dados['dataRenovacao']
            ],
        ];


        Mail::to($mailTo)->send(new EmailPagamento($dadosEmail));
    }
}
