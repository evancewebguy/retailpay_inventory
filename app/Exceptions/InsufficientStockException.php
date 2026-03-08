<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class InsufficientStockException extends Exception
{
    protected $productName;
    protected $available;
    protected $requested;

    public function __construct($productName, $available, $requested, $message = "")
    {
        $this->productName = $productName;
        $this->available = $available;
        $this->requested = $requested;
        
        $message = $message ?: "Insufficient stock for {$productName}. Available: {$available}, Requested: {$requested}";
        
        parent::__construct($message);
    }

    public function getProductName()
    {
        return $this->productName;
    }

    public function getAvailable()
    {
        return $this->available;
    }

    public function getRequested()
    {
        return $this->requested;
    }

    public function render(Request $request)
    {
        return response()->json([
            'message' => 'Insufficient stock',
            'error' => $this->getMessage(),
            'type' => 'insufficient_stock',
            'details' => [
                'product' => $this->productName,
                'available' => $this->available,
                'requested' => $this->requested
            ]
        ], 400);
    }
}