<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'store_id' => 'required|exists:stores,id',
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1'
        ];
    }

    public function messages()
    {
        return [
            'items.required' => 'At least one item is required',
            'items.*.quantity.min' => 'Quantity must be at least 1'
        ];
    }
}