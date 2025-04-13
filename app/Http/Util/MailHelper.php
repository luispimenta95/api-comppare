<?php

namespace App\Http\Util;

use App\Mail\EmailAssinatura;
use App\Mail\EmailPagamento;
use App\Mail\EmailWelcome;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailConvite;


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
                'nomePlano' => $dados['nomePlano'],
                'url' => $dados['url']
            ]
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

    public static function confirmacaoAssinatura(array $dados, string $mailTo): void //testing webhocks
    {
        $dadosEmail = [
            'to' => $mailTo,
            'body' => [
                'nome' => $dados['nome']
            ]
        ];


        Mail::to($mailTo)->send(new EmailAssinatura($dadosEmail));
    }

    public static function emailConvite(array $dados, string $mailTo): void //testing webhocks
    {
        $dadosEmail = [
            'to' => $mailTo,
            'body' => [
                'nomePasta' => $dados['nomePasta'],
                'url' => env('FRONT_URL')
            ]
        ];


        Mail::to($mailTo)->send(new EmailConvite($dadosEmail));
    }

}
