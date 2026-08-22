<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id,is_active,1'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'amount_paid' => ['sometimes', 'nullable', 'integer', 'min:0'],
            // Tax is never client-supplied — always server-computed from the shop's
            // tax_percent setting, same "never trust client money math" principle as total.
            'discount' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Keranjang tidak boleh kosong.',
            'items.*.qty.min' => 'Jumlah item minimal 1.',
            'items.*.product_id.exists' => 'Produk tidak tersedia.',
        ];
    }
}
