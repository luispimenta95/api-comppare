<?php

namespace App\Http\Util;

use App\Models\Pastas;
use App\Models\Usuarios;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

/**
 * Classe utilitária com funções auxiliares do sistema
 * 
 * Contém constantes importantes, validações, operações de arquivo
 * e métodos auxiliares utilizados em toda a aplicação.
 */
class Helper


{
    const ID_PERFIL_ADMIN = 1;
    const ID_PERFIL_USUARIO = 2;
    const ID_PERFIL_CONVIDADO = 3;
    const ID_PLANO_CONVIDADO = 7;
    const PERIODICIDADE_MENSAL = 'MENSAL';
    const PERIODICIDADE_ANUAL = 'ANUAL';
    const PERIODICIDADE_SEMESTRAL = 'SEMESTRAL';

    const ATIVO = 1;

    const TEMPO_GRATUIDADE = 7;
    const LIMITE_FOTOS = 2;

    const LIMITE_TAGS = 5;

    const LIMITE_PASTAS = 10;
    
    const LIMITE_SUBPASTAS = 5;

    const TEMPO_RENOVACAO_MENSAL = 30;
    const TEMPO_RENOVACAO_ANUAL = 360;

    const TEMPO_RENOVACAO_SEMESTRAL = 180;

    const STATUS_APROVADO = 'paid';
    const STATUS_AGUARDANDO_APROVACAO = 'waiting';
    const STATUS_CANCELADO = 'CANCELLED';
    const MOEDA = "BRL";
    const TIPO_RENOVACAO_MENSAL = 'months';
    const TIPO_RENOVACAO_DIARIA = 'days';
    const DIA_COBRANCA = 05;
    const STATUS_ATIVO = 'active';
    const STATUS_AUTORIZADO = 'authorized';

    const QUANTIDADE = 1;
    const INTERVALO_MENSAL = 1;
    const INTERVALO_ANUAL = 12;

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
            -11 => 'Error: Limite de criação de pastas mensal atingido.',
            -12 => 'Error: Erro ao realizar venda do plano de assinatura.',
            -13 => 'Error: Usuário bloqueado por inatividade superior a 180 dias.'

        ];
    }

    /**
     * Valida se um CPF é válido
     * 
     * Verifica se o CPF possui formato correto e dígitos verificadores válidos.
     * 
     * @param string $cpf - CPF a ser validado
     * @return bool - True se válido, false caso contrário
     */
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

    /**
     * Valida se todos os campos obrigatórios estão presentes no request
     * 
     * Verifica se todos os campos requeridos foram fornecidos na requisição.
     * 
     * @param Request $request - Requisição HTTP
     * @param array $requiredFields - Array com nomes dos campos obrigatórios
     * @return mixed - Retorna erro se algum campo obrigatório estiver ausente
     */
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

    /**
     * Cria uma nova pasta no sistema de arquivos
     * 
     * Cria estrutura de diretórios no storage público para organizar
     * arquivos de usuários.
     * 
     * @param string $folderName - Nome da pasta a ser criada
     * @return array - Array com 'path' da pasta criada ou null se erro
     */
    public static function createFolder(string $folderName): array
    {
        $folderName = str_replace(" ", "_", $folderName);

        // Usa o disk "public" para criar dentro de storage/app/public
        if (Storage::disk('public')->makeDirectory($folderName)) {
            $fullPath = Storage::disk('public')->path($folderName);

            return [
                'message' => 'Pasta criada com sucesso!',
                'path' => $fullPath,
            ];
        }

        return ['message' => 'Erro ao criar a pasta.'];
    }

    /**
     * Remove uma pasta do sistema de arquivos
     * 
     * Deleta permanentemente uma pasta e todo seu conteúdo
     * do storage do sistema.
     * 
     * @param string $folderName - Nome da pasta a ser removida
     * @return JsonResponse - Resposta JSON com status da operação
     */
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
                [
                    'message' => 'Processo finalizado com sucesso',
                    'code'  => 200
                ]
            );
        } else {
            // Em caso de erro na requisição
            return response()->json(['message' => 'Erro ao realizar request', 'code'  => 500], 500);
        }
    }

    /**
     * Verifica se uma data já passou (está no passado)
     * 
     * Compara uma data específica com a data atual para determinar
     * se já expirou.
     * 
     * @param mixed $date - Data a ser verificada
     * @return bool - True se a data já passou, false caso contrário
     */
    // Checa se uma data informada já passou. Caso positivo, return true | return false
    public static function checkDateIsPassed($date): bool
    {
        //Call to a member function lt() on string
        $dataAtual = Carbon::now();
        return  Carbon::parse($date)->lt($dataAtual);
    }

    /**
     * Relaciona pastas e subpastas a um usuário
     * 
     * Estabelece a relação entre um usuário e suas pastas,
     * incluindo subpastas recursivamente.
     * 
     * @param Pastas $pasta - Pasta a ser relacionada
     * @param Usuarios $usuario - Usuário para relacionar a pasta
     * @return void
     */
    public static function relacionarPastas(Pastas $pasta, Usuarios $usuario): void
    {

        $subpastas = $pasta->subpastas;

        foreach ($subpastas as $subpasta) {
            // Adiciona o usuário à subpasta
            $subpasta->usuarios()->attach($usuario->id);

            // Chama a função recursivamente para adicionar as subpastas das subpastas
            self::relacionarPastas($subpasta, $usuario);
        }
    }

    /**
     * Formata o caminho da pasta para URL amigável
     * 
     * @param Pastas $pasta
     * @param string $separador
     * @return string
     */
    public static function formatFriendlyPath(Pastas $pasta, string $separador = '/'): string
    {
        $caminho = [];
        $pastaAtual = $pasta;
        
        // Constrói o caminho do filho para o pai
        while ($pastaAtual) {
            $nomeFormatado = strtolower(str_replace(' ', '-', $pastaAtual->nome));
            array_unshift($caminho, $nomeFormatado);
            $pastaAtual = $pastaAtual->pastaPai;
        }
        
        return implode($separador, $caminho);
    }

    /**
     * Formata o caminho da imagem
     * 
     * @param string $caminho
     * @return string
     */
    public static function formatImagePath(string $caminho): string
    {
        // Remove barras extras e normaliza o caminho
        $caminho = trim($caminho, '/');
        return $caminho ? '/' . $caminho : '';
    }

    /**
     * Formata uma URL completa para imagem
     * 
     * @param string $imagePath
     * @return string
     */
    public static function formatImageUrl(string $imagePath): string
    {
        // Se o path não começa com http, formata como URL completa
        if (!str_starts_with($imagePath, 'http')) {
            // Se começa com /storage, remove e reconstrói
            if (str_starts_with($imagePath, '/storage/')) {
                $relativePath = str_replace('/storage/', '', $imagePath);
            } else {
                $relativePath = trim($imagePath, '/');
            }
            $appUrl = config('app.url');
            return $appUrl . '/storage/' . $relativePath;
        }
        
        return $imagePath;
    }

    /**
     * Formata uma URL completa para a pasta física
     * 
     * @param Pastas $pasta
     * @return string
     */
    public static function formatFolderUrl(Pastas $pasta): string
    {
        $appUrl = config('app.url');
        
        // Remove o caminho base e formata como URL
        $relativePath = str_replace(
            env('PUBLIC_PATH', '/home/u757410616/domains/comppare.com.br/public_html/api-comppare/storage/app/public/'),
            '',
            $pasta->caminho
        );
        $relativePath = trim($relativePath, '/');
        
        return $appUrl . '/storage/' . $relativePath;
    }
}
