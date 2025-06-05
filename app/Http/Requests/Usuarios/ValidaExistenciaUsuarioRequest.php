<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Foundation\Http\FormRequest;

class ValidaExistenciaUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cpf' => 'required|string',
            'telefone' => 'nullable|string',
            'email' => 'nullable|string|email',
        ];
    }
}
