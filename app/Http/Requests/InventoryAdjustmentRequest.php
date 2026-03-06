<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'type' => ['required', Rule::in(['ADDITION', 'REDUCTION', 'CORRECTION'])],
            'reason' => ['required', Rule::in([
                'DAMAGE',
                'LOST',
                'FOUND',
                'COUNT_CORRECTION',
                'EXPIRY',
                'OTHER'
            ])],
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.new_quantity' => 'required|integer|min:0',
            'items.*.reason' => 'nullable|string|max:500'
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required for adjustment',
            'items.*.new_quantity.min' => 'Quantity cannot be negative'
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('items')) {
            $items = collect($this->items)->map(function ($item) {
                return [
                    'product_id' => (int) $item['product_id'],
                    'new_quantity' => (int) $item['new_quantity'],
                    'reason' => $item['reason'] ?? null
                ];
            })->toArray();

            $this->merge(['items' => $items]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if user has access to the store
            $user = auth()->user();
            if ($user && !$user->canAccessStore($this->store_id)) {
                $validator->errors()->add(
                    'store_id',
                    'You do not have permission to adjust inventory at this store'
                );
            }

            // Validate that at least one item has a quantity change
            if ($this->has('items')) {
                $hasChange = false;
                foreach ($this->items as $item) {
                    $inventory = \App\Models\Inventory::where('store_id', $this->store_id)
                        ->where('product_id', $item['product_id'])
                        ->first();

                    $currentQty = $inventory ? $inventory->quantity : 0;
                    if ($currentQty != $item['new_quantity']) {
                        $hasChange = true;
                        break;
                    }
                }

                if (!$hasChange) {
                    $validator->errors()->add(
                        'items',
                        'No quantity changes detected in adjustment items'
                    );
                }
            }
        });
    }
}