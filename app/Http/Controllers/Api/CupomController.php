<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Cupom;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Enums\HttpCodesEnum;

class CupomController extends Controller
{
    // Atualizando para utilizar a enum HttpCodesEnum

    public function __construct()
    {
        // Inicializando a variável codes para utilizar a enum HttpCodesEnum

    }

    public function index(): JsonResponse
    {
        $response = [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'totalCupons' => Cupom::count(),
            'cuponsAtivos' => Cupom::where('status', 1)->count(),
            'data' => Cupom::all(),
        ];

        return response()->json($response);
    }

    public function saveTicket(Request $request): JsonResponse
    {
        $campos = ['cupom', 'percentualDesconto', 'quantidadeDias'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $cupom = Cupom::create([
            'cupom' => $request->cupom,
            'percentualDesconto' => $request->percentualDesconto,
        ]);

        if (isset($cupom->id)) {
            $cupom->dataExpiracao = $cupom->created_at->addDays($request->quantidadeDias);
            $cupom->save();

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

    public function getTicketDiscount(Request $request): JsonResponse
    {
        $cupom = Cupom::find($request->idCupom);

        $response = isset($cupom->id) ? [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'data' => $cupom,
        ] : [
            'codRetorno' => HttpCodesEnum::NotFound->value,
            'message' => HttpCodesEnum::NotFound->description(),
        ];

        return response()->json($response);
    }

    public function atualizarDados(Request $request): JsonResponse
    {
        $campos = ['idCupom', 'percentualDesconto', 'quantidadeDias'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $cupom = Cupom::findOrFail($request->idCupom);

        if (isset($cupom->id)) {
            $cupom->cupom = $request->cupom;
            $cupom->percentualDesconto = $request->percentualDesconto;
            $cupom->quantidadeDias = $request->quantidadeDias;
            $cupom->save();

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

    public function atualizarStatus(Request $request): JsonResponse
    {
        $campos = ['idCupom', 'status'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $cupom = Cupom::findOrFail($request->idCupom);

        if (isset($cupom->id)) {
            $cupom->status = $request->status;
            $cupom->save();

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

    public function checkStatusTicket(Request $request): JsonResponse
    {
        $campos = ['cupom'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $cupom = Cupom::where('cupom', strtoupper(str_replace(' ', '', $request->cupom)))->firstOrFail();

        $response = $cupom->status == 1 ? [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'data' => $cupom,
        ] : [
            'codRetorno' => HttpCodesEnum::BadRequest->value,
            'message' => HttpCodesEnum::InactiveTicket->description(),
        ];

        return response()->json($response);
    }
}
