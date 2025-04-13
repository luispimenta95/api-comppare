<?php

namespace App\Http\Controllers;


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
        $campos = ['email', 'idUsuario', 'idPasta'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ]);
        }

        $pasta = Pastas::find($request->idPasta);
        $usuario = Usuarios::find($request->idUsuario);

        if (!$usuario) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::UserNotFound->description()
            ]);
        }

        $plano = Planos::find($usuario->idPlano);

        if (!$plano) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::PlanNotFoundForUser->description()
            ]);
        }

        $convitesAtuais = $usuario->convites()->count();

        if ($convitesAtuais >= $plano->quantidadeConvites) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::InvitesLimit->description(),
            ]);
        }

        $convite = Convite::create([
            'idUsuario' => $request->idUsuario,
            'idPasta' => $pasta->id,
            'email' => $request->email,
        ]);

        $pasta->usuarios()->attach($usuario->id);
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
