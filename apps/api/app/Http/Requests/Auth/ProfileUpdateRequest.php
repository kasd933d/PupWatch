<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'timezone' => ['sometimes', 'string', 'max:64', 'timezone'],
            'current_password' => ['required_with:password', 'string', 'current_password'],
            'password' => ['sometimes', 'string', 'min:10', 'confirmed'],
        ];
    }
}
