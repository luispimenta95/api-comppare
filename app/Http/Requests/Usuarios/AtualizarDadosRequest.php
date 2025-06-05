<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarDadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'email' => 'required|email|unique:usuarios,email',
            'cpf' => 'required|string|cpf|unique:usuarios,cpf',
            'telefone' => 'required|string|size:11',
            'nascimento' => 'required|date|before:today',
            'senha' => 'required|string|min:8',
            'idUsuario' => 'required|exists:usuarios,id',
        ];
    }
}
