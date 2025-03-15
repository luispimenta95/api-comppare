<?php

namespace App\Mail;

class EmailAssinatura extends BaseEmail
{
    public $subject = 'Assinatura criada com sucesso.'; // Assunto específico

    /**
     * Retorna o template markdown específico para o e-mail de boas-vindas.
     *
     * @return string
     */
    protected function getMarkdownTemplate()
    {
        return 'emails.template_signature'; // Template específico
    }
}
