<?php

namespace App\Http\Util;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class Helper


{
    const int ID_PERFIL_ADMIN = 1;
    const int ID_PERFIL_USUARIO = 2;

    const int ATIVO = 1;

    const int TEMPO_GRATUIDADE = 15;
    const int LIMITE_FOTOS = 2;

    const int LIMITE_TAGS = 5;

    const int LIMITE_PASTAS = 10;


    const int TEMPO_RENOVACAO = 30;

    const string STATUS_APROVADO = 'paid';
    const string STATUS_AGUARDANDO_APROVACAO = 'waiting';
    const string STATUS_CANCELADO = 'CANCELLED';
    const string MOEDA = "BRL";
    const TIPO_RENOVACAO_MENSAL = 'months';
    const string TIPO_RENOVACAO_DIARIA = 'days';
    const int DIA_COBRANCA = 05;
    const string STATUS_ATIVO = 'active';
    const string STATUS_AUTORIZADO = 'authorized';

    const int QUANTIDADE = 1;
    const int INTERVALO_MENSAL = 1;
    const int INTERVALO_ANUAL= 12;

    /**
     * Retorna todos os códigos HTTP e suas descrições.
     *
     * @return array
     */
    public static function getHttpCodes(): array
    {
        return [
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',
            226 => 'IM Used',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
            //Especificos do COMPPARE APP
            -1 => 'Error: Erro desconhecido',
            -2 => 'Error: Erro ao validar CPF',
            -3 => 'Error: Erro ao validar CNPJ',
            -4 => 'Error: Erro ao validar Email',
            -5 => 'Error: Erro ao validar Telefone',
            -6 => 'Error: CPF já cadastrado no banco de dados',
            -7 => 'Error: Período de gratuidade expirado. Por favor, atualize sua assinatura adquirindo um novo plano.',
            -8 => 'Error: Assinatura exiprada. Por favor, atualize sua assinatura adquirindo um novo plano.',
            -9 => 'Error: A request possui campos obrigatórios não preenchidos ou inválidos.',
            -10 => 'Error: O pagamento ainda não foi realizado.',
            -11 => 'Error: Limite de criação de pastas mensal atingido.' ,
            -12 => 'Error: Erro ao realizar venda do plano de assinatura.'
        ];
    }

    public static function validaCPF(string $cpf): bool
    {

        // Extrai somente os números
        $cpf = preg_replace('/[^0-9]/is', '', $cpf);

        // Verifica se foi informado todos os digitos corretamente
        if (strlen($cpf) != 11) {
            return false;
        }

        // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Faz o calculo para validar o CPF
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        return true;
    }

    public static function validarRequest(Request $request, array $requiredFields): mixed
    {
        $camposNulos = [];

        foreach ($requiredFields as $field) {
            if (is_null($request->input($field))) {
                $camposNulos[] = $field; // Add the field to the null fields array
            }
        }

        return empty($camposNulos) ? true : $camposNulos; // Return true if valid, otherwise return all null fields
    }



    public static function createFolder(string $folderName): JsonResponse
    {


        // Cria a pasta no caminho storage/app/
        if (Storage::makeDirectory($folderName)) {
            // Gera o caminho completo da pasta criada
            $fullPath = Storage::path($folderName);

            return response()->json([
                'message' => 'Pasta criada com sucesso!',
                'path' => $fullPath, // Retorna o path no response
            ]);
        }

        return response()->json(['message' => 'Erro ao criar a pasta.'], 500);
    }

    public static function deleteFolder(string $folderName): JsonResponse
    {
        $delete = true;
        if (!Storage::deleteDirectory($folderName)) {
            $delete = false;
        }
        return response()->json([
            'message' => $delete ? 'Pasta deletada com sucesso!' : 'Erro ao deletar a pasta.'
        ]);
    }

    public static  function makeRequest(string $url, array $data): mixed
    {
        $route = url($url);

        // Faz a requisição POST para o endpoint interno
        $response = Http::post($route, $data);
        // Verifique a resposta
        if ($response->successful()) {
            // A requisição foi bem-sucedida
            return response()->json(
                ['message' => 'Processo finalizado com sucesso',
                'code'  => 200
                ]);
        } else {
            // Em caso de erro na requisição
            return response()->json(['message' => 'Erro ao realizar request', 'code'  => 500],500);
        }
    }
}
