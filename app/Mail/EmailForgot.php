<?php

namespace App\Mail;

class EmailForgot extends BaseEmail
{
    public $subject = 'Solicitação de recuperação de senha'; // Assunto específico

    /**
     * Retorna o template markdown específico para o e-mail de boas-vindas.
     *
     * @return string
     */
    protected function getMarkdownTemplate()
    {
        return 'emails.template_forgot'; // Template específico
    }
}
