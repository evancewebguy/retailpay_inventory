<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'from_store_id' => [
                'required',
                'exists:stores,id',
                Rule::notIn([$this->to_store_id]) // Prevent transfer to same store
            ],
            'to_store_id' => 'required|exists:stores,id|different:from_store_id',
            'expected_delivery_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id|distinct',
            'items.*.quantity' => 'required|integer|min:1'
        ];

        // Additional validation for update scenarios
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['status'] = [
                'sometimes',
                Rule::in(['PENDING', 'PROCESSING', 'SHIPPED', 'RECEIVED', 'CANCELLED'])
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'from_store_id.not_in' => 'Source and destination stores cannot be the same',
            'to_store_id.different' => 'Source and destination stores must be different',
            'items.required' => 'At least one item is required for transfer',
            'items.min' => 'At least one item is required for transfer',
            'items.*.product_id.required' => 'Please select a product for each item',
            'items.*.product_id.distinct' => 'Duplicate products are not allowed in the same transfer',
            'items.*.quantity.min' => 'Quantity must be at least 1',
            'expected_delivery_date.after' => 'Expected delivery date must be in the future'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean up items array to remove empty entries
        if ($this->has('items')) {
            $items = array_filter($this->items, function ($item) {
                return !empty($item['product_id']) && !empty($item['quantity']);
            });
            
            $this->merge([
                'items' => array_values($items) // Re-index array
            ]);
        }

        // Ensure numeric values are integers
        if ($this->has('from_store_id')) {
            $this->merge([
                'from_store_id' => (int) $this->from_store_id
            ]);
        }

        if ($this->has('to_store_id')) {
            $this->merge([
                'to_store_id' => (int) $this->to_store_id
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if source and destination stores belong to valid branches
            $this->validateStoreBranches($validator);
            
            // Check if user has permission to transfer between these stores
            $this->validateTransferPermission($validator);
            
            // Check if items have sufficient stock (for new transfers)
            if ($this->isMethod('POST')) {
                $this->validateSufficientStock($validator);
            }
        });
    }

    /**
     * Validate that stores belong to valid branches.
     */
    protected function validateStoreBranches($validator): void
    {
        $fromStore = \App\Models\Store::find($this->from_store_id);
        $toStore = \App\Models\Store::find($this->to_store_id);

        if ($fromStore && $toStore) {
            // Check if stores are active
            if (!$fromStore->is_active) {
                $validator->errors()->add(
                    'from_store_id', 
                    'Source store is not active'
                );
            }

            if (!$toStore->is_active) {
                $validator->errors()->add(
                    'to_store_id', 
                    'Destination store is not active'
                );
            }
        }
    }

    /**
     * Validate that user has permission to transfer between these stores.
     */
    protected function validateTransferPermission($validator): void
    {
        $user = auth()->user();

        if (!$user) {
            return;
        }

        // Administrators can transfer anywhere
        if ($user->hasRole('Administrator')) {
            return;
        }

        $fromStore = \App\Models\Store::find($this->from_store_id);

        // Branch managers can only transfer from stores in their branch
        if ($user->hasRole('Branch Manager')) {
            if ($fromStore && $fromStore->branch_id !== $user->branch_id) {
                $validator->errors()->add(
                    'from_store_id',
                    'You can only transfer from stores in your branch'
                );
            }
        }

        // Store managers can only transfer from their own store
        if ($user->hasRole('Store Manager')) {
            if ($this->from_store_id != $user->store_id) {
                $validator->errors()->add(
                    'from_store_id',
                    'You can only transfer from your own store'
                );
            }
        }
    }

    /**
     * Validate that items have sufficient stock.
     */
    protected function validateSufficientStock($validator): void
    {
        if (!$this->from_store_id || !$this->items) {
            return;
        }

        foreach ($this->items as $index => $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) {
                continue;
            }

            $inventory = \App\Models\Inventory::where('store_id', $this->from_store_id)
                ->where('product_id', $item['product_id'])
                ->first();

            $availableQuantity = $inventory ? $inventory->available_quantity : 0;

            if ($availableQuantity < $item['quantity']) {
                $product = \App\Models\Product::find($item['product_id']);
                $productName = $product ? $product->name : 'Product';

                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "Insufficient stock for {$productName}. Available: {$availableQuantity}"
                );
            }
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'from_store_id' => 'source store',
            'to_store_id' => 'destination store',
            'expected_delivery_date' => 'expected delivery date',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Format items data
        if ($this->has('items')) {
            $items = collect($this->items)->map(function ($item) {
                return [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity']
                ];
            })->toArray();

            $this->merge([
                'items' => $items
            ]);
        }
    }
}