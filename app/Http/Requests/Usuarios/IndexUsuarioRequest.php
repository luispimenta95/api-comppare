<?php 

namespace App\Http\Requests\Usuarios;

use Illuminate\Foundation\Http\FormRequest;

class IndexUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:0,1',
            'nome' => 'nullable|string|max:255',
        ];
    }
}
