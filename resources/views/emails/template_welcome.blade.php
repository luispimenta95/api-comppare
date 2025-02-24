@component('mail::message')
    Olá, **{{ $dados['nomeUsuario'] }}**!

    Estamos felizes pela sua compra no nosso site do **{{ $dados['nomePlano'] }}**.

    <x-mail::button :url="$dados['url']">
        Clique aqui para realizar o pagamento.
    </x-mail::button>

    Obrigado,<br>
    {{ config('app.name') }}
@endcomponent
