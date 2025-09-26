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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\TextUI\Help;

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
                'message' => 'A pasta não pertence ao usuário informado.'
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

        $pasta->usuario()->attach($usuario->id);
        Helper::relacionarPastas($pasta, $usuario);
        if ($convite) {
            $usuario->convites()->save($convite);
            $usuario->increment('quantidadeConvites');

            $dadosEmail = ['nomePasta' => $pasta->nome];
            MailHelper::confirmacaoAssinatura($dadosEmail, $request->email);

            return response()->json([
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
            ]);
        }

        return response()->json([
            'codRetorno' => HttpCodesEnum::BadRequest->value,
            'message' => HttpCodesEnum::SendInviteError->description(),
        ]);
    }

}
