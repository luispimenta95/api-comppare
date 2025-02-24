<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComppareEmailWelcome extends Mailable
{
    use Queueable, SerializesModels;

    protected $dados; // Dados do e-mail
    protected $fromName = 'Comppare'; // Nome padrão do remetente
    public $subject = 'Assinatura solicitada com sucesso'; // Assunto padrão do e-mail
    public $mailTo; // Destinatário

    /**
     * Cria uma nova instância do e-mail.
     *
     * @param array $dados
     */
    public function __construct(array $dados)
    {
        if (!isset($dados['to']) || !filter_var($dados['to'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("O campo 'to' deve conter um endereço de e-mail válido.");
        }

        $this->dados = $dados['body'] ?? []; // Inicializa os dados do corpo do e-mail
        $this->mailTo = $dados['to']; // Inicializa o destinatário
    }

    /**
     * Constrói o conteúdo do e-mail.
     */
    public function build()
    {
        return $this->from($this, $this->fromName)
            ->subject($this->subject)
            ->markdown('emails.template_welcome') // Usa o template de markdown
            ->with('dados', $this->dados); // Passa os dados ao template
    }
}
