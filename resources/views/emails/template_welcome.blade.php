<!DOCTYPE html>
<html lang="pt-BR">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1 );
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #333;
        }
        .content {
            font-size: 16px;
            color: #555;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .btn-container {
            text-align: center;
        }
        .btn {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            font-size: 16px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .footer {
            font-size: 14px;
            color: #888;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="content">
        <p>Olá, {{ $dados['nome'] }}!</p>
        <p>Obrigado por se interessar em nosso produto {{$dados['nomePlano']}}. Para confirmar o seu cadastro, por favor, clique no botão abaixo para confirmar sua compra:</p>
    </div>
    <div class="btn-container">
        <a href="{{ $dados['url'] }}" class="btn">Confirmar Compra</a>
    </div>
    <div class="footer">
        <p>Se você não se cadastrou em nosso site, ignore este e-mail.</p>
        <p>Atenciosamente, <br>
            {{ config('app.name') }}</p>
        <br>
        <p>&copy; {{ date('Y') }} Todos os direitos reservados.</p>
    </div>
</div>
</body>
</html>
