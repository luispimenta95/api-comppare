<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarSenhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'senha' => 'required|string|min:8',
            'cpf' => 'required|string|exists:usuarios,cpf'
        ];
    }
}
