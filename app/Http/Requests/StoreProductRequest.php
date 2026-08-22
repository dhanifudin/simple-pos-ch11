<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        $isUpdate = $productId !== null;

        return [
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($productId)],
            'price' => ['required', 'integer', 'min:0'],
            // Stock is set at creation only; changing an existing product's stock
            // goes through ProductController::adjustStock() so it's always logged.
            'stock' => $isUpdate ? ['sometimes'] : ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori wajib dipilih.',
            'sku.unique' => 'SKU sudah digunakan produk lain.',
        ];
    }
}
