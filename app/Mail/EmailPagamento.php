<?php

namespace App\Mail;

class EmailPagamento extends BaseEmail
{
    public $subject = 'Pagamento realizado com sucesso.'; // Assunto específico

    /**
     * Retorna o template markdown específico para o e-mail de boas-vindas.
     *
     * @return string
     */
    protected function getMarkdownTemplate()
    {
        return 'emails.template_payment'; // Template específico
    }
}
