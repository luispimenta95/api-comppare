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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #e74c3c;
            font-size: 24px;
        }
        .content {
            font-size: 16px;
            color: #555;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #e74c3c;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .btn-container {
            text-align: center;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            font-size: 16px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
            margin: 5px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-secondary {
            background-color: #95a5a6;
        }
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        .footer {
            font-size: 14px;
            color: #888;
            text-align: center;
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .highlight {
            color: #e74c3c;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🚫 Cancelamento Confirmado</h1>
    </div>
    
    <div class="content">
        <p>Olá, {{ $dados['nome'] }}!</p>

        <p>Confirmamos o cancelamento da sua assinatura em nossa plataforma e à partir de agora, você não será mais cobrado.</p>

        <p>Lamentamos ver você partir! 😔 Sua opinião é muito importante para nós.</p>
        
        <p>Se você mudou de ideia ou deseja reativar sua assinatura a qualquer momento, estaremos aqui para te receber de volta!</p>
    </div>
    
    
    <div class="footer">
        <p>Se você não solicitou este cancelamento, entre em contato conosco imediatamente.</p>
        <p><strong>Suporte:</strong> suporte@comppare.com.br</p>
        <p>Atenciosamente,<br>
        {{ config('app.name') }}</p>
        <p>&copy; {{ date('Y') }} Todos os direitos reservados.</p>
    </div>
</div>
</body>
</html>
