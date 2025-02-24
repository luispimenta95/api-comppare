<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

abstract class BaseEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $dados; // Dados para o e-mail
    public $fromName = 'Comppare';

    /**
     * Cria uma nova instância do e-mail.
     *
     * @param array $dados
     */

    /**
     * Configurações comuns para o envio.
     */
    public function buildBase()
    {
        return $this->from($this->to, $this->fromName)
            ->subject($this->subject);
    }
}
