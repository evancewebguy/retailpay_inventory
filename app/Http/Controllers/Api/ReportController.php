<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ReportController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'permission:view reports',
            // new Middleware('permission:view reports'),
        ];
    }


    /**
     * Stock valuation report
     */
    public function stockValuation(Request $request)
    {
        $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'store_id' => 'nullable|exists:stores,id',
            'category' => 'nullable|string'
        ]);

        $query = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->join('branches', 'stores.branch_id', '=', 'branches.id')
            ->select(
                'branches.name as branch_name',
                'stores.name as store_name',
                'products.sku',
                'products.name as product_name',
                'inventories.quantity',
                'products.cost_price',
                'products.selling_price',
                DB::raw('inventories.quantity * products.cost_price as total_cost'),
                DB::raw('inventories.quantity * products.selling_price as total_value')
            );

        // Apply filters
        if ($request->branch_id) {
            $query->where('stores.branch_id', $request->branch_id);
        }

        if ($request->store_id) {
            $query->where('inventories.store_id', $request->store_id);
        }

        if ($request->category) {
            $query->where('products.category', $request->category);
        }

        // Restrict by user permissions
        $user = auth()->user();
        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $query->where('stores.branch_id', $user->branch_id);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('inventories.store_id', $user->store_id);
            }
        }

        $results = $query->get();

        // Calculate totals
        $totals = [
            'total_cost' => $results->sum('total_cost'),
            'total_value' => $results->sum('total_value'),
            'potential_profit' => $results->sum('total_value') - $results->sum('total_cost')
        ];

        return response()->json([
            'data' => $results,
            'totals' => $totals,
            'generated_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Movement history report
     */
    public function movementHistory(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'store_id' => 'nullable|exists:stores,id',
            'product_id' => 'nullable|exists:products,id',
            'movement_type' => 'nullable|in:SALE,TRANSFER,ADJUSTMENT,PROCUREMENT,RETURN,DAMAGE,LOST'
        ]);

        $query = InventoryMovement::with(['product', 'fromStore', 'toStore', 'creator'])
            ->whereBetween('created_at', [
                $request->from_date . ' 00:00:00',
                $request->to_date . ' 23:59:59'
            ]);

        if ($request->store_id) {
            $query->where(function ($q) use ($request) {
                $q->where('from_store_id', $request->store_id)
                  ->orWhere('to_store_id', $request->store_id);
            });
        }

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->movement_type) {
            $query->where('movement_type', $request->movement_type);
        }

        // Restrict by user permissions
        $user = auth()->user();
        if (!$user->hasRole('Administrator')) {
            $accessibleStoreIds = [];

            if ($user->hasRole('Branch Manager')) {
                $accessibleStoreIds = Store::where('branch_id', $user->branch_id)
                    ->pluck('id')
                    ->toArray();
            } elseif ($user->hasRole('Store Manager')) {
                $accessibleStoreIds = [$user->store_id];
            }

            $query->where(function ($q) use ($accessibleStoreIds) {
                $q->whereIn('from_store_id', $accessibleStoreIds)
                  ->orWhereIn('to_store_id', $accessibleStoreIds);
            });
        }

        $movements = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 50);

        // Calculate summary statistics
        $summary = [
            'total_movements' => $movements->total(),
            'by_type' => $movements->groupBy('movement_type')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_quantity' => $group->sum('quantity')
                    ];
                })
        ];

        return response()->json([
            'data' => $movements->items(),
            'summary' => $summary,
            'pagination' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total()
            ]
        ]);
    }

    /**
     * Sales report
     */
    public function salesReport(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'store_id' => 'nullable|exists:stores,id',
            'interval' => 'nullable|in:daily,weekly,monthly'
        ]);

        $interval = $request->interval ?? 'daily';
        $dateFormat = match($interval) {
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d'
        };

        $query = DB::table('sales')
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                DB::raw("DATE_FORMAT(sales.created_at, '{$dateFormat}') as period"),
                DB::raw('COUNT(DISTINCT sales.id) as total_sales'),
                DB::raw('SUM(sale_items.quantity) as total_items_sold'),
                DB::raw('SUM(sales.grand_total) as total_revenue'),
                DB::raw('SUM(sales.grand_total - (sale_items.quantity * products.cost_price)) as total_profit')
            )
            ->whereBetween('sales.created_at', [
                $request->from_date . ' 00:00:00',
                $request->to_date . ' 23:59:59'
            ]);

        if ($request->store_id) {
            $query->where('sales.store_id', $request->store_id);
        }

        // Restrict by user permissions
        $user = auth()->user();
        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $storeIds = Store::where('branch_id', $user->branch_id)->pluck('id');
                $query->whereIn('sales.store_id', $storeIds);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('sales.store_id', $user->store_id);
            }
        }

        $results = $query->groupBy('period')
            ->orderBy('period', 'asc')
            ->get();

        // Calculate overall totals
        $totals = [
            'total_sales' => $results->sum('total_sales'),
            'total_items_sold' => $results->sum('total_items_sold'),
            'total_revenue' => $results->sum('total_revenue'),
            'total_profit' => $results->sum('total_profit'),
            'average_sale_value' => $results->sum('total_sales') > 0 
                ? $results->sum('total_revenue') / $results->sum('total_sales') 
                : 0
        ];

        return response()->json([
            'interval' => $interval,
            'data' => $results,
            'totals' => $totals,
            'generated_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Product performance report
     */
    public function productPerformance(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $limit = $request->limit ?? 20;

        $query = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->select(
                'products.id',
                'products.sku',
                'products.name as product_name',
                DB::raw('SUM(sale_items.quantity) as total_quantity_sold'),
                DB::raw('COUNT(DISTINCT sales.id) as number_of_sales'),
                DB::raw('SUM(sale_items.total) as total_revenue'),
                DB::raw('AVG(sale_items.quantity) as average_quantity_per_sale')
            )
            ->whereBetween('sales.created_at', [
                $request->from_date . ' 00:00:00',
                $request->to_date . ' 23:59:59'
            ])
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->orderBy('total_quantity_sold', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $query,
            'generated_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Export report as CSV
     */
    public function export(Request $request)
    {
        $request->validate([
            'type' => 'required|in:valuation,movements,sales,products',
            'format' => 'required|in:csv'
        ]);

        // Generate filename
        $filename = "report_{$request->type}_" . now()->format('Y-m-d_His') . '.csv';

        // Get data based on report type
        $data = match($request->type) {
            'valuation' => $this->getValuationData($request),
            'movements' => $this->getMovementsData($request),
            'sales' => $this->getSalesData($request),
            'products' => $this->getProductsData($request),
        };

        // Create CSV
        $handle = fopen('php://temp', 'r+');
        
        // Add headers
        fputcsv($handle, array_keys($data->first() ?? []));
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($handle, (array) $row);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    private function getValuationData($request)
    {
        return DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->select(
                'stores.name as store',
                'products.sku',
                'products.name as product',
                'inventories.quantity',
                'products.cost_price',
                'products.selling_price',
                DB::raw('inventories.quantity * products.cost_price as total_cost'),
                DB::raw('inventories.quantity * products.selling_price as total_value')
            )
            ->get();
    }

    private function getMovementsData($request)
    {
        return InventoryMovement::with(['product', 'fromStore', 'toStore'])
            ->latest()
            ->limit(1000)
            ->get()
            ->map(function ($movement) {
                return [
                    'date' => $movement->created_at->toDateTimeString(),
                    'type' => $movement->movement_type,
                    'product' => $movement->product->name,
                    'sku' => $movement->product->sku,
                    'quantity' => $movement->quantity,
                    'from_store' => $movement->fromStore->name ?? 'N/A',
                    'to_store' => $movement->toStore->name ?? 'N/A',
                    'reference' => $movement->reference_number
                ];
            });
    }

    private function getSalesData($request)
    {
        return DB::table('sales')
            ->join('stores', 'sales.store_id', '=', 'stores.id')
            ->select(
                'sales.sale_number',
                'stores.name as store',
                'sales.created_at as date',
                'sales.grand_total as amount',
                'sales.payment_status',
                'sales.status'
            )
            ->orderBy('sales.created_at', 'desc')
            ->limit(1000)
            ->get();
    }

    private function getProductsData($request)
    {
        return DB::table('products')
            ->select(
                'sku',
                'name',
                'selling_price as price',
                'cost_price as cost',
                DB::raw('selling_price - cost_price as profit_margin')
            )
            ->where('is_active', true)
            ->get();
    }
}
