<?php
namespace App\Mail;


class ComppareEmailWelcome extends BaseEmail{

    public $dados; // Dados que você deseja passar para o e-mail
    public $to;
    public $subject = 'Bem vindo ao Comppare!';


    /**
    * Cria uma nova instância do e-mail.
    */
    public function __construct(Array $dados)
    {
    $this->dados = $dados['body'];
    $this->to = $dados['to'];
    }

    /**
    * Constrói o conteúdo da mensagem.
    */
    public function build()
    {
    return $this->from( $this->to, $this->fromName)
    ->subject($this->subject)
    ->markdown('emails.template_welcome') // O template criado
    ->with('dados', $this->dados); // Passando dados para o template
    }
}
