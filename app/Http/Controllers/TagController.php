<?php

namespace App\Http\Controllers;

use App\Http\Util\Helper;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TagController extends Controller
{
    private array $codes;
    private $userLogado;
    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
         $this->userLogado = Auth::user(); //

    }
    /**
     * Display a listing of the resource.
     */
    public function index() : object
    {
        $tags = Tag::where('status', 1)->count();
        $response = [
            'codRetorno' => 200,
            'message' => $this->codes[200],
            'totalTags' => Tag::count(),
            'tagsAtivas' => $tags,
            'data' => Tag::all()

        ];
        return response()->json($response);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function cadastrarTag(Request $request) : object
    {
        $campos = ['nome', 'descricao', 'valor', 'quantidadeTags'];

        $campos = Helper::validarRequest($request, $campos);
        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }
        $exists = Tag::where('nome', $request->nome)->exists();
        if ($exists) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-10]
            ];
           return response()->json($response);
        }
        Tag::create([

            'nome' => $request->nome,
            'descricao' => $request->descricao,
            'idUsuarioCriador' => $request->usuario
        ]);
        $response = [
            'codRetorno' => 200,
            'message' => $this->codes[200]
            ];
        return response()->json($response);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function atualizarStatus(Request $request): object
    {
        $campos = ['idTag'];

        $campos = Helper::validarRequest($request, $campos);
        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }
        $tag = Tag::findOrFail($request->idTag);
        if (isset($tag->id)) {
            $tag->status = $request->status;
            $tag->save();
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ];
        } else {

            $response = [
                'codRetorno' => 500,
                'message' => $this->codes[500]
            ];
        }
        return response()->json($response);
    }

}
