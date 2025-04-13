<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

abstract class BaseEmail extends Mailable
{
use Queueable, SerializesModels;

protected $dados;
protected $sender;
protected $fromName = 'Comppare'; // Nome padrão do remetente
public $subject; // O assunto será definido nas subclasses
public $mailTo; // Destinatário do e-mail

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
$this->sender = env('MAIL_FROM_ADDRESS');
}

/**
* Constrói o conteúdo do e-mail.
*
* @return $this
*/
public function build()
{
return $this->from($this->sender, $this->fromName)
->subject($this->subject) // Subjeto dinâmico
->view($this->getMarkdownTemplate()) // Template dinâmico
->with('dados', $this->dados); // Passa os dados ao template
}

/**
* Retorna o template markdown específico para cada e-mail.
*
* @return string
*/
abstract protected function getMarkdownTemplate();
}
