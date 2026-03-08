<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => 'required|exists:stores,id',
            'type' => 'required|in:ADDITION,REDUCTION,CORRECTION',
            'reason' => 'required|in:DAMAGE,LOST,FOUND,COUNT_CORRECTION,EXPIRY,OTHER',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.new_quantity' => 'required|integer|min:0',
            'items.*.reason' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required',
            'items.*.new_quantity.min' => 'Quantity cannot be negative',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure items have the correct structure
        if ($this->has('items')) {
            $items = collect($this->items)->map(function ($item) {
                return [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'new_quantity' => (int) ($item['new_quantity'] ?? 0),
                    'reason' => $item['reason'] ?? null,
                ];
            })->toArray();

            $this->merge([
                'items' => $items,
                'store_id' => (int) $this->store_id,
            ]);
        }
    }
}