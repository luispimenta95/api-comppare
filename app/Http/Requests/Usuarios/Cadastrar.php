<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\HttpCodesEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
 // Certifique-se de que o enum está importado corretamente

class Cadastrar extends FormRequest
{
    public function messages(): array
    {
        return [
            'senha.required' => 'A senha é obrigatória.',
            'senha.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'senha.regex' => ' A senha deve conter: Pelo menos um número; Apresentar pelo menos um caractere especial ($,#,@,!,etc); Ter ao menos uma letra minúscula; Ter ao menos uma letra maiúscula;.',
        ];
    }
    public function authorize(): bool
    {
        return true; // Permitir que qualquer um acesse
    }

    public function rules(): array
    {
        return [
            'primeiroNome' => 'required|string|max:255',
            'sobrenome' => 'required|string|max:255',
            'apelido' => 'nullable|string|max:255',
            'cpf' => 'required|string|size:11',
            'senha' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/'
            ],
            'telefone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'idPlano' => 'required|integer|exists:planos,id',
            'nascimento' => 'required|date_format:d/m/Y',
        ];
    }


    protected function failedValidation(Validator $validator)
{
    $errors = $validator->errors()->toArray();

    throw new HttpResponseException(response()->json([
        'codRetorno' => HttpCodesEnum::InvalidPassword->value, // ou -9 (MissingRequiredFields)
        'message' => $this->formatErrorMessage($errors),
        'erros' => $errors
    ], HttpCodesEnum::BadRequest->value));
}

private function formatErrorMessage(array $errors): string
{
    // Se quiser customizar, pode extrair só a primeira mensagem:
    $firstError = reset($errors)[0] ?? 'Erro de validação.';
    return $firstError;
}
    public function attributes(): array
    {
        return [
            'primeiroNome' => 'Primeiro Nome',
            'sobrenome' => 'Sobrenome',
            'apelido' => 'Apelido',
            'cpf' => 'CPF',
            'senha' => 'Senha',
            'telefone' => 'Telefone',
            'email' => 'Email',
            'idPlano' => 'Plano',
            'nascimento' => 'Data de Nascimento'
        ];
    }
}
