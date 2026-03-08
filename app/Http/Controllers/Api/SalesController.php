<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\InventoryService;
use App\Http\Requests\SaleRequest;
use App\Models\Sale;
use Illuminate\Routing\Controllers\Middleware;
use App\Exceptions\InsufficientStockException;


class SalesController extends Controller
{

    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:create sales', only: ['store']),
            new Middleware('permission:view sales', only: ['index', 'show']),
        ];
    }

    public function index(Request $request)
    {
        $sales = Sale::with(['store', 'items.product', 'creator'])
            ->when($request->store_id, function ($query, $storeId) {
                return $query->where('store_id', $storeId);
            })
            ->when($request->from_date, function ($query, $date) {
                return $query->whereDate('created_at', '>=', $date);
            })
            ->when($request->to_date, function ($query, $date) {
                return $query->whereDate('created_at', '<=', $date);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($sales);
    }

    public function store(SaleRequest $request)
    {
        
        try {
            // Check if user has access to this store
            if (!auth()->user()->canAccessStore($request->store_id)) {
                return response()->json([
                    'message' => 'Unauthorized to sell from this store'
                ], 403);
            }


            $sale = $this->inventoryService->processSale(
                $request->store_id,
                $request->items,
                auth()->id(),
                $request->customer_id ?? null
            );

            dd(vars: $sale);

            return response()->json([
                'message' => 'Sale completed successfully',
                'data' => $sale->load(['items.product', 'store'])
            ], 201);

        } catch (\Exception $e) {

            $statusCode = 400;

            $response = [
                'message' => 'Sale failed',
                'error' => $e->getMessage()
            ];

            if ($e instanceof InsufficientStockException) {
                $response['type'] = 'insufficient_stock';
            }elseif ($e instanceof \PDOException) {
                $response['error'] = 'A database error occurred';
                $statusCode = 500;
            }
        }
    }

    public function show(Sale $sale)
    {
        // Check access
        if (!auth()->user()->canAccessStore($sale->store_id)) {
            return response()->json([
                'message' => 'Unauthorized to view this sale'
            ], 403);
        }

        return response()->json(
            $sale->load(['items.product', 'store', 'creator', 'customer'])
        );
    }

    public function destroy(Sale $sale)
    {
        if (!auth()->user()->hasRole('Administrator')) {
            return response()->json([
                'message' => 'Only administrators can delete sales'
            ], 403);
        }

        $sale->delete();

        return response()->json([
            'message' => 'Sale deleted successfully'
        ]);
    }
}

