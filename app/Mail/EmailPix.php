<?php

namespace App\Mail;

class EmailPix extends BaseEmail
{
    public $subject = 'PIX Gerado - Código de Pagamento'; // Assunto específico

    /**
     * Retorna o template markdown específico para o e-mail de PIX.
     *
     * @return string
     */
    protected function getMarkdownTemplate()
    {
        return 'emails.template_pix'; // Template específico
    }
}
