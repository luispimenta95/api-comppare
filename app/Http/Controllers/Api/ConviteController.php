<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\HttpCodesEnum;
use App\Http\Util\Helper;
use App\Http\Util\MailHelper;
use App\Models\Convite;
use App\Models\Pastas;
use App\Models\Planos;
use App\Models\Usuarios;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConviteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): JsonResponse
    {
        $campos = ['email', 'usuario', 'pasta'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ]);
        }

        $pasta = Pastas::find($request->pasta);
        $usuario = Usuarios::find($request->usuario);

        if (!$usuario) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::UserNotFound->description()
            ]);
        }

        // Verifica se a pasta pertence ao usuário informado
        if (!$pasta || $pasta->idUsuario != $usuario->id) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::UserNotFolderOwner->description()
            ]);
        }

        // Verifica se já existe convite para a pasta
        $conviteExistente = Convite::where('idPasta', $pasta->id)->exists();
        if ($conviteExistente) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::FolderAlreadyShared->description()
            ]);
        }

        $plano = Planos::find($usuario->idPlano);

        if (!$plano) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::PlanNotFoundForUser->description()
            ]);
        }

        $convitesAtuais = $usuario->quantidadeConvites ?? 0;

        if ($convitesAtuais >= $plano->quantidadeConvites) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::InvitesLimit->description(),
            ]);
        }

            $convite = Convite::create([
                'idUsuario' => $request->usuario,
                'idPasta' => $pasta->id,
                'email' => $request->email,
            ]);

            // Evita duplicidade na tabela pasta_usuario
            if (!$pasta->usuario()->where('usuario_id', $usuario->id)->exists()) {
                $pasta->usuario()->attach($usuario->id);
            }
            Helper::relacionarPastas($pasta, $usuario);
            if ($convite) {
                $usuario->increment('quantidadeConvites');

                $dadosEmail = ['nomePasta' => $pasta->nome];
                //MailHelper::confirmacaoAssinatura($dadosEmail, $request->email);

                return response()->json([
                    'codRetorno' => HttpCodesEnum::OK->value,
                    'message' => 'Compartilhamento de pasta criado com sucesso.'
                ]);
            }

        return response()->json([
            'codRetorno' => HttpCodesEnum::BadRequest->value,
            'message' => HttpCodesEnum::SendInviteError->description(),
        ]);
    }
/*
       * Processa convites pendentes para o usuário logado (por e-mail)
     * Associa as pastas dos convites ao usuário, se houver.
     * POST /api/usuarios/processar-convites
     * Body: { "email": "email@dominio.com" }
     */
    public function processarConvitesPendentes(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:usuarios,email',
        ]);

        $usuario = Usuarios::where('email', $request->email)->first();
        if (!$usuario) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => 'Usuário não encontrado.'
            ], 404);
        }

        $convites = Convite::where('email', $usuario->email)->get();
        $pastasVinculadas = [];
        foreach ($convites as $convite) {
            $pasta = Pastas::find($convite->idPasta);
            if ($pasta && !$pasta->usuario()->where('usuario_id', $usuario->id)->exists()) {
                $pasta->usuario()->attach($usuario->id);
                $pastasVinculadas[] = $pasta->id;
            }
        }

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => count($pastasVinculadas) > 0 ? 'Convites processados e pastas vinculadas.' : 'Nenhum convite pendente encontrado.',
            'pastas_vinculadas' => $pastasVinculadas
        ]);
    }

    /**
     * Remove um convite do banco de dados usando o id da pasta
     * 
     * Valida os dados de entrada, verifica se o convite existe e o exclui.
     * Exemplo de request:
     * Post /api/convite/excluir
     * Content-Type: application/json
     * Body:
     * {
     *   "idPasta": 10
     * }
     * @param Request $request - Deve conter: idPasta
     * @return JsonResponse - Confirmação da exclusão ou erro
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'idPasta' => 'required|exists:convites,idPasta',
        ]);

        $convite = Convite::where('idPasta', $request->idPasta)->first();

        if (!$convite) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => 'Convite não encontrado para esta pasta.'
            ], 404);
        }

        // Remove o usuário convidado da relação pasta_usuario
        $pasta = Pastas::find($convite->idPasta);
        if ($pasta && $convite->idUsuario) {
            // Remove todos os vínculos do usuário convidado com a pasta
            DB::table('pasta_usuario')
                ->where('pasta_id', $convite->idPasta)
                ->delete();
        }

        $convite->delete();

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => 'Convite excluído com sucesso.'
        ]);
    }

}
