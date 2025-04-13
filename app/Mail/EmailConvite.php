<?php

namespace App\Mail;

class EmailConvite extends BaseEmail
{
    public $subject = 'Você recebeu um convite para acessar a Compare.'; // Assunto específico

    /**
     * Retorna o template markdown específico para o e-mail de boas-vindas.
     *
     * @return string
     */
    protected function getMarkdownTemplate()
    {
        return 'emails.template_invite'; // Template específico
    }
}
