@component('mail::message')
<div style="text-align: center; font-family: Arial, sans-serif; color: #333;">
    <!-- Header Section with Logo -->
    <!-- <img src="{{ config('app.logo') }}" alt="{{ config('app.name') }}" style="max-width: 150px; margin-bottom: 20px;" />
-->
    <!-- Greeting Section -->
    <h2 style="font-size: 24px; color: #4CAF50;">Olá, <strong>{{ $dados['nome'] }}</strong>!</h2>

    <!-- Purchase Info -->
    <p style="font-size: 18px; color: #555;">Estamos felizes em receber o seu pagamento.</p>

    <p style="font-size: 16px; margin-top: 30px; color: #777;">Obrigado por confiar em nós! Seu acesso estará ativo até o dia {{ date('d/m/Y', strtotime($dados['dataRenovacao'])) }} .</p>


    <!-- Footer Section -->
    <footer style="margin-top: 40px; font-size: 14px; color: #aaa;">
        <p>{{ config('app.name') }}</p>
        <p>&copy; {{ date('Y') }} Todos os direitos reservados.</p>
    </footer>
</div>
@endcomponent