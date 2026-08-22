<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A blank password field on the edit form submits as an empty string, not an
     * absent key — normalize it to null so "sometimes|nullable" treats it as
     * "leave unchanged" instead of failing the min:8 rule against "".
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('password') === '') {
            $this->merge(['password' => null]);
        }
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isUpdate = $userId !== null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => $isUpdate
                ? ['sometimes', 'nullable', Password::min(8)->numbers()]
                : ['required', Password::min(8)->numbers()],
            'role' => ['required', Rule::in(['admin', 'kasir'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah digunakan pengguna lain.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ];
    }
}
