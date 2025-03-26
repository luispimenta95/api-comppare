<?php

namespace App\Http\Controllers;


use App\Enums\HttpCodesEnum;
use App\Http\Util\Helper;
use App\Http\Util\MailHelper;
use App\Models\Convite;
use App\Models\Pastas;
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
        $campos = ['email', 'idUsuario', 'idPasta'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }
        $pasta = Pastas::find($request->idPasta);

        $convite = Convite::create([
            'idUsuario' => $request->idUsuario,
            'idPasta' => $pasta->id,
            'email' => $request->email
        ]);
        if ($convite) {

            $dadosEmail = [
                'nomePasta' => $pasta->nome,
            ];


            MailHelper::confirmacaoAssinatura($dadosEmail, $request->email);
        } else{
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::SendInviteError->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $response = [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
        ];
        return response()->json($response);
    }

    /**
     * Store a newly created resource in storage.
     */

}
