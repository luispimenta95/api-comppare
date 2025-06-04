<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\HttpCodesEnum; // Certifique-se de que o enum está importado corretamente

class Cadastrar extends FormRequest
{
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

    public function messages(): array
    {
        return [
            'senha.regex' =>  HttpCodesEnum::InvalidPassword->description()
            
        ];
    }
}
