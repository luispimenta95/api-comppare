<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Cupom;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Enums\HttpCodesEnum;

class QuestoesController extends Controller
{

    public function __construct()
    {

    }

    public function listar(): JsonResponse
    {
        $response = [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'data' => Question::all(),
        ];

        return response()->json($response);
    }

    public function saveQuestion(Request $request): JsonResponse
    {
        $campos = ['pergunta', 'resposta'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $question = Question::create([
            'pergunta' => $request->pergunta,
            'resposta' => $request->resposta,
        ]);

        if (isset($question->id)) {


            $response = [
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
            ];
        } else {
            $response = [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => HttpCodesEnum::InternalServerError->description(),
            ];
        }

        return response()->json($response);
    }


}
