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
            color: #333;
            font-size: 24px;
        }
        .pix-icon {
            font-size: 48px;
            color: #32BCAD;
        }
        .content {
            font-size: 16px;
            color: #555;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .pix-code {
            background-color: #f8f9fa;
            border: 2px dashed #32BCAD;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
            margin: 20px 0;
            text-align: center;
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 12px;
            margin: 20px 0;
        }
        .valor {
            font-size: 28px;
            font-weight: bold;
            color: #32BCAD;
            text-align: center;
            margin: 20px 0;
        }
        .btn-container {
            text-align: center;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            background-color: #32BCAD;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            font-size: 16px;
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background-color: #28a085;
        }
        .details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .details table {
            width: 100%;
            border-collapse: collapse;
        }
        .details td {
            padding: 5px;
            border-bottom: 1px solid #e9ecef;
        }
        .details td:first-child {
            font-weight: bold;
            color: #666;
            width: 40%;
        }
        .footer {
            font-size: 14px;
            color: #888;
            text-align: center;
            margin-top: 20px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 12px;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="pix-icon">💳</div>
        <h1>PIX Gerado com Sucesso!</h1>
    </div>
    
    <div class="content">
        <p>Olá, {{ $dados['nome'] ?? 'Cliente' }}!</p>
        <p>Seu código PIX foi gerado com sucesso. Utilize o código abaixo para realizar o pagamento:</p>
    </div>

    <div class="valor">
        R$ {{ number_format($dados['valor'] ?? 0, 2, ',', '.') }}
    </div>

    <div class="info-box">
        <strong>📱 Como pagar:</strong><br>
        1. Abra o app do seu banco<br>
        2. Escolha a opção PIX<br>
        3. Selecione "Copiar e Colar" ou "PIX Copia e Cola"<br>
        4. Cole o código abaixo<br>
        5. Confirme o pagamento
    </div>

    <div class="pix-code">
        {{ $dados['pixCopiaECola'] ?? 'Código PIX não disponível' }}
    </div>

    @if(isset($dados['contrato']) || isset($dados['objeto']))
    <div class="details">
        <h3>📋 Detalhes do Pagamento</h3>
        <table>
            @if(isset($dados['contrato']))
            <tr>
                <td>Contrato:</td>
                <td>{{ $dados['contrato'] }}</td>
            </tr>
            @endif
            @if(isset($dados['objeto']))
            <tr>
                <td>Serviço:</td>
                <td>{{ $dados['objeto'] }}</td>
            </tr>
            @endif
            @if(isset($dados['periodicidade']))
            <tr>
                <td>Periodicidade:</td>
                <td>{{ $dados['periodicidade'] }}</td>
            </tr>
            @endif
            @if(isset($dados['dataInicial']) && isset($dados['dataFinal']))
            <tr>
                <td>Período:</td>
                <td>{{ date('d/m/Y', strtotime($dados['dataInicial'])) }} a {{ date('d/m/Y', strtotime($dados['dataFinal'])) }}</td>
            </tr>
            @endif
            <tr>
                <td>TXID:</td>
                <td>{{ $dados['txid'] ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>
    @endif

    <div class="warning">
        <strong>⚠️ Importante:</strong><br>
        • O código PIX é válido por tempo limitado<br>
        • Após o pagamento, você receberá uma confirmação<br>
        • Guarde este e-mail para sua segurança
    </div>

    <div class="footer">
        <p>Se você não solicitou este PIX, por favor, ignore este e-mail</p>
        <p>Atenciosamente,<br>{{ config('app.name') }}</p>
        <p>&copy; {{ date('Y') }} Todos os direitos reservados.</p>
    </div>
</div>
</body>
</html>
