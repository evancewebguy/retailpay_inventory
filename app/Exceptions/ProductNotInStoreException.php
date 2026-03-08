<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class ProductNotInStoreException extends Exception
{
    protected $productName;
    protected $storeName;

    public function __construct($productName, $storeName)
    {
        $this->productName = $productName;
        $this->storeName = $storeName;
        
        parent::__construct("{$productName} is not available in {$storeName}");
    }

    public function render(Request $request)
    {
        return response()->json([
            'message' => 'Product not available',
            'error' => $this->getMessage(),
            'type' => 'product_not_in_store',
            'details' => [
                'product' => $this->productName,
                'store' => $this->storeName
            ]
        ], 400);
    }
}