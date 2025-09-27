<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarPlanoUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cpf' => 'required|string|exists:usuarios,cpf',
            'plano' => 'required|exists:planos,id',
        ];
    }
}
