<?php

namespace App\Http\Controllers\Api;

use App\Enums\HttpCodesEnum;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
/**
 * Controller para gerenciamento de usuários
 * 
 * Responsável por todas as operações relacionadas aos usuários do sistema,
 * incluindo cadastro, autenticação, atualização de dados, gerenciamento de planos,
 * alteração de senhas e funcionalidades de recuperação de senha.
 */
class AdminController extends Controller
{
    public function importUsersFromCSV(Request $request)
    {
        dd('chegou aqui');
        $file = $request->file('csv_file');

        if (!$file || !$file->isValid()) {
            return response()->json(['error' => 'Arquivo CSV inválido.'], HttpCodesEnum::BAD_REQUEST->value);
        }

        try {
            $path = $file->getRealPath();
            $handle = fopen($path, 'r');

            if ($handle === false) {
                return response()->json(['error' => 'Não foi possível abrir o arquivo CSV.'], HttpCodesEnum::INTERNAL_SERVER_ERROR->value);
            }

            $header = fgetcsv($handle, 1000, ',');

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $userData = array_combine($header, $data);
                dd($userData);

                // Validação dos dados do usuário
                if (empty($userData['name']) || empty($userData['email']) || empty($userData['password'])) {
                    continue; // Pula registros com dados incompletos
                }

                // Verifica se o usuário já existe
                $existingUser = DB::table('users')->where('email', $userData['email'])->first();

                if ($existingUser) {
                    continue; // Pula usuários que já existem
                }

                // Insere o novo usuário no banco de dados
                DB::table('users')->insert([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => bcrypt($userData['password']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            fclose($handle);

            return response()->json(['message' => 'Usuários importados com sucesso.'], HttpCodesEnum::OK->value);
        } catch (\Exception $e) {
            Log::error('Erro ao importar usuários do CSV: ' . $e->getMessage());
            return response()->json(['error' => 'Ocorreu um erro ao importar os usuários.'], HttpCodesEnum::INTERNAL_SERVER_ERROR->value);
        }
    }
}

