<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Planos;
use App\Models\TransacaoFinanceira;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Usuarios;
use Illuminate\Support\Facades\Hash;
use App\Http\Util\Helper;
use App\Http\Util\MailHelper;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;



class UsuarioController extends Controller
{
    private array $codes = [];
    private int $planoGratuito = 1;
    private int $tempoRenovacao = 30;


    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
    }

    public function index(): JsonResponse
    {
        $usuariosAtivos = Usuarios::where('status', 1)->count();
        $response = [
            'codRetorno' => 200,
            'message' => $this->codes[200],
            'totalUsuarios' => Usuarios::count(),
            'usuariosAtivos' => $usuariosAtivos,
            'data' => Usuarios::all()

        ];
        return response()->json($response);
    }

    public function cadastrarUsuario(Request $request): JsonResponse
    {
        $campos = ['nome', 'senha', 'cpf', 'telefone', 'idPlano', 'email', 'nascimento', 'cardToken'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        if (!Helper::validaCPF($request->cpf)) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-2]
            ];
            return response()->json($response);
        } else {
            if ($this->confirmaUser($request)) {
                $response = [
                    'codRetorno' => 400,
                    'message' => $this->codes[-6]
                ];
                return response()->json($response);
            } else {

                $dataNascimento = Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d');
                $usuario = Usuarios::create([
                    'nome' => $request->nome,
                    'senha' => bcrypt($request->senha), //
                    'cpf' => $request->cpf,
                    'telefone' => $request->telefone,
                    'email' => $request->email,
                    'nascimento' => $dataNascimento,
                    'idPlano' => $request->idPlano,
                    'idPerfil' => Helper::ID_PERFIL_USUARIO
                ]);

                if (isset($usuario->id)) {
                    $dadosAssinatura = [
                        'usuario' => $usuario->id,
                        'plano' => $request->idPlano,
                        'token' => $request->cardToken
                    ];

                    $createSignature = json_decode(Helper::makeRequest('/api/vendas/criar-assinatura', $dadosAssinatura));

                    if ($createSignature['code'] == 200) {
                        $response = [
                            'codRetorno' => 200,
                            'message' => $this->codes[200],
                        ];

                    }else{
                        $response = [
                            'codRetorno' => 500,
                            'message' => $this->codes[-12]
                            ];
                    }
                }else {
                    $response = [
                        'codRetorno' => 500,
                        'message' => $this->codes[500]
                    ];
                }
                return response()->json($response);
            }
        }
    }

    public function getUser(Request $request): JsonResponse
    {
        $campos = ['idUsuario'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }
        $usuario = Usuarios::find($request->idUsuario);
        isset($usuario->id) ?
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200],
                'data' => $usuario
            ] : $response = [
                'codRetorno' => 404,
                'message' => $this->codes[404]
            ];
        return response()->json($response);
    }

    public function atualizarDados(Request $request): JsonResponse
    {
        $campos = ['nome', 'senha', 'email', 'cpf', 'telefone' ,'nascimento'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }
        if (!Helper::validaCPF($request->cpf)) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[400]
            ];
            return response()->json($response);
        } else {
            $usuario = Usuarios::findOrFail($request->idUsuario);
            $dataNascimento = Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d');

            if (isset($usuario->id)) {
                $usuario->nome = $request->nome;
                $usuario->senha = bcrypt($request->senha);
                $usuario->cpf = $request->cpf;
                $usuario->telefone = $request->telefone;
                $usuario->email = $request->email;
                $usuario->dataNascimento = $dataNascimento;
                $usuario->save();
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
        }
        return response()->json($response);
    }


    public function atualizarStatus(Request $request): JsonResponse
    {
        $usuario = Usuarios::findOrFail($request->idUsuario);
        if (isset($usuario->id)) {
            $usuario->status = $request->status;
            $usuario->save();
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

    public function autenticar(Request $request): JsonResponse
    {

        // Validar os dados de entrada
        $campos = ['cpf', 'senha'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }
        // Recuperar o usuário com base no CPF
        $user = Usuarios::where('cpf', $request->input('cpf'))->first();
        if (!$user) {
            $response = [
                'codRetorno' => 404,
                'message' => $this->codes[404]
            ];
            return response()->json($response);
        }

        $response = $this->checaPermissoes($user, $request);

        return response()->json($response);
    }

    private function confirmaUser(Request $request): mixed
    {
        $campos = ['cpf'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }
        $exists = Usuarios::where('cpf', $request->cpf)->exists();
        return $exists;
    }

    public function validaExistenciaUsuario(Request $request): JsonResponse
    {
        $existe = $this->confirmaUser($request);
        $existe ?
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ] :
            $response = [
                'codRetorno' => 404,
                'message' => $this->codes[404]
            ];
        return response()->json($response);
    }

    public function atualizarSenha(Request $request): JsonResponse
    {
        $campos = ['cpf', 'senha'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $usuario = Usuarios::findOrFail($request->cpf);
        if (isset($usuario->cpf)) {
            $usuario->senha = bcrypt($request->senha);
            $usuario->save();

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
        // Criar email para recuperar a senha
        return response()->json($response);
    }

    private function checaPermissoes(Usuarios $user, Request $request): JsonResponse
    {
        $osTime = Carbon::now()->setTimezone('America/Recife');
        $dataLimiteCompra = Carbon::parse($user->dataLimiteCompra)->setTimezone('America/Recife');

        // Verificar se a senha fornecida corresponde à senha armazenada no banco
        if (!$user || !Hash::check($request->input('senha'), $user->senha)) {
            $response = [
                'codRetorno' => 404,
                'message' => $this->codes[404]
            ];
        } else {
            $token = JWTAuth::fromUser($user);
            //Verifica validade de perio de testes para planos pagos
            if (($user->idPlano != $this->planoGratuito) && $dataLimiteCompra > $osTime) {
                $response = [
                    'codRetorno' => 400,
                    'message' => $this->codes[-7]
                ];
                return response()->json($response);
            }
            //Verifica data do ultimo pagamento
            if (($user->idPlano != $this->planoGratuito) && $user->dataUltimoPagamento == null) {
                $response = [
                    'codRetorno' => 400,
                    'message' => $this->codes[-7]
                ];
                return response()->json($response);
            }
            if ($user->idPlano != $this->planoGratuito) {
                $daLimiteAcesso = $user->dataUltimoPagamento->addDays($this->tempoRenovacao)->setTimezone('America/Recife');

                if (($user->idPlano != $this->planoGratuito) && $daLimiteAcesso > $osTime) {
                    $response = [
                        'codRetorno' => 400,
                        'message' => $this->codes[-8]
                    ];
                    return response()->json($response);
                }
            } else {
                $pastas = $user->pastas->map(function ($pasta) {
                    return [
                        'nome' => $pasta->nome,
                        'caminho' => $pasta->caminho
                    ];
                });

                $response = [
                    'codRetorno' => 200,
                    'message' => $this->codes[200],
                    'token' => $token,
                    'dados' => $user->only('id', 'nome', 'cpf', 'telefone'),
                    'pastas' => $pastas
                ];
            }
        }
        return response()->json($response);
    }
}
