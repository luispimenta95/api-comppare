<?php

namespace App\Mail;

class EmailForgot extends BaseEmail
{
    public $subject = 'Assinatura solicitada com sucesso'; // Assunto específico

    /**
     * Retorna o template markdown específico para o e-mail de boas-vindas.
     *
     * @return string
     */
    protected function getMarkdownTemplate()
    {
        return 'emails.template_welcome'; // Template específico
    }
}
