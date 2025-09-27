<?php

namespace App\Mail;

class EmailCancelamento extends BaseEmail
{
    public $subject = 'Cancelamento de assinatura confirmado'; // Assunto específico

    /**
     * Retorna o template markdown específico para o e-mail de cancelamento.
     *
     * @return string
     */
    protected function getMarkdownTemplate()
    {
        return 'emails.template_cancelamento'; // Template específico
    }
}
