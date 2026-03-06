<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class ReportController extends Controller implements HasMiddleware
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
            new Middleware('auth:sanctum'),
            new Middleware('permission:view reports'),
        ];
    }

    /**
     * Get all reports summary (dashboard for reports)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Get date range (default to last 30 days)
        $toDate = $request->to_date ?? now()->toDateString();
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();

        // Summary statistics
        $summary = [
            'total_sales' => $this->getTotalSales($fromDate, $toDate, $user),
            'total_movements' => $this->getTotalMovements($fromDate, $toDate, $user),
            'total_products' => Product::count(),
            'total_stores' => Store::count(),
            'low_stock_count' => $this->getLowStockCount($user),
            'inventory_value' => $this->getInventoryValue($user),
        ];

        // Sales overview chart data
        $salesOverview = $this->getSalesOverview($fromDate, $toDate, $user);

        // Top products
        $topProducts = $this->getTopProducts($fromDate, $toDate, $user, 5);

        // Movement types breakdown
        $movementTypes = $this->getMovementTypesBreakdown($fromDate, $toDate, $user);

        // Recent movements
        $recentMovements = InventoryMovement::with(['product', 'fromStore', 'toStore'])
            ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                $accessibleStoreIds = $this->getAccessibleStoreIds($user);
                $query->where(function ($q) use ($accessibleStoreIds) {
                    $q->whereIn('from_store_id', $accessibleStoreIds)
                      ->orWhereIn('to_store_id', $accessibleStoreIds);
                });
            })
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'reference' => $movement->reference_number,
                    'type' => $movement->movement_type,
                    'product' => $movement->product->name,
                    'quantity' => $movement->quantity,
                    'from_store' => $movement->fromStore->name ?? 'N/A',
                    'to_store' => $movement->toStore->name ?? 'N/A',
                    'created_at' => $movement->created_at->toDateTimeString(),
                ];
            });

        // Store performance
        $storePerformance = $this->getStorePerformance($fromDate, $toDate, $user, 5);

        return response()->json([
            'summary' => $summary,
            'sales_overview' => $salesOverview,
            'top_products' => $topProducts,
            'movement_types' => $movementTypes,
            'recent_movements' => $recentMovements,
            'store_performance' => $storePerformance,
            'date_range' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'generated_at' => now()->toDateTimeString(),
        ]);
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
                'inventories.reserved_quantity',
                DB::raw('inventories.quantity - inventories.reserved_quantity as available_quantity'),
                'products.cost_price',
                'products.selling_price',
                DB::raw('inventories.quantity * products.cost_price as total_cost'),
                DB::raw('inventories.quantity * products.selling_price as total_value'),
                DB::raw('(inventories.quantity - inventories.reserved_quantity) * products.selling_price as available_value')
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
            'available_value' => $results->sum('available_value'),
            'potential_profit' => $results->sum('total_value') - $results->sum('total_cost'),
            'total_items' => $results->count(),
            'total_quantity' => $results->sum('quantity'),
        ];

        // Group by branch for summary
        $byBranch = $results->groupBy('branch_name')->map(function ($items) {
            return [
                'total_value' => $items->sum('total_value'),
                'total_cost' => $items->sum('total_cost'),
                'item_count' => $items->count(),
            ];
        });

        return response()->json([
            'data' => $results,
            'totals' => $totals,
            'by_branch' => $byBranch,
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
            'movement_type' => 'nullable|in:SALE,TRANSFER,ADJUSTMENT,PROCUREMENT,RETURN,DAMAGE,LOST',
            'per_page' => 'nullable|integer|min:1|max:100'
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
            $accessibleStoreIds = $this->getAccessibleStoreIds($user);
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
            'total_quantity' => $movements->sum('quantity'),
            'by_type' => $movements->groupBy('movement_type')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_quantity' => abs($group->sum('quantity'))
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
                DB::raw('SUM(sales.discount_amount) as total_discount'),
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
            'total_discount' => $results->sum('total_discount'),
            'total_profit' => $results->sum('total_profit'),
            'average_sale_value' => $results->sum('total_sales') > 0 
                ? $results->sum('total_revenue') / $results->sum('total_sales') 
                : 0,
            'average_items_per_sale' => $results->sum('total_sales') > 0
                ? $results->sum('total_items_sold') / $results->sum('total_sales')
                : 0,
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
            'store_id' => 'nullable|exists:stores,id',
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
                'products.category',
                DB::raw('SUM(sale_items.quantity) as total_quantity_sold'),
                DB::raw('COUNT(DISTINCT sales.id) as number_of_sales'),
                DB::raw('SUM(sale_items.total) as total_revenue'),
                DB::raw('SUM(sale_items.quantity * products.cost_price) as total_cost'),
                DB::raw('SUM(sale_items.total) - SUM(sale_items.quantity * products.cost_price) as total_profit'),
                DB::raw('AVG(sale_items.quantity) as average_quantity_per_sale')
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

        $results = $query->groupBy('products.id', 'products.sku', 'products.name', 'products.category')
            ->orderBy('total_quantity_sold', 'desc')
            ->limit($limit)
            ->get();

        // Add margin percentage
        $results = $results->map(function ($item) {
            $item->profit_margin = $item->total_revenue > 0 
                ? round(($item->total_profit / $item->total_revenue) * 100, 2)
                : 0;
            return $item;
        });

        return response()->json([
            'data' => $results,
            'generated_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Low stock report
     */
    public function lowStock(Request $request)
    {
        $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'threshold' => 'nullable|integer|min:0'
        ]);

        $threshold = $request->threshold ?? 0;
        $user = auth()->user();

        $query = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->select(
                'stores.id as store_id',
                'stores.name as store_name',
                'products.id as product_id',
                'products.sku',
                'products.name as product_name',
                'inventories.quantity',
                'inventories.reserved_quantity',
                DB::raw('inventories.quantity - inventories.reserved_quantity as available_quantity'),
                'inventories.reorder_point',
                'products.selling_price',
                DB::raw('(inventories.quantity - inventories.reserved_quantity) * products.selling_price as available_value')
            )
            ->whereRaw('inventories.quantity - inventories.reserved_quantity <= GREATEST(inventories.reorder_point, ?)', [$threshold]);

        if ($request->store_id) {
            $query->where('inventories.store_id', $request->store_id);
        }

        // Restrict by user permissions
        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $query->where('stores.branch_id', $user->branch_id);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('inventories.store_id', $user->store_id);
            }
        }

        $results = $query->orderBy('available_quantity', 'asc')
            ->paginate($request->per_page ?? 50);

        return response()->json([
            'data' => $results->items(),
            'pagination' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total()
            ],
            'total_value_at_risk' => $results->sum('available_value'),
            'generated_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Export report as CSV
     */
    public function export(Request $request)
    {
        $request->validate([
            'type' => 'required|in:valuation,movements,sales,products,low-stock',
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
            'low-stock' => $this->getLowStockData($request),
        };

        // Create CSV
        $handle = fopen('php://temp', 'r+');
        
        // Add headers
        if ($data->isNotEmpty()) {
            fputcsv($handle, array_keys((array) $data->first()));
        }
        
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

    // ==================== PRIVATE HELPER METHODS ====================

    private function getAccessibleStoreIds($user)
    {
        if ($user->hasRole('Administrator')) {
            return Store::pluck('id')->toArray();
        }
        
        if ($user->hasRole('Branch Manager')) {
            return Store::where('branch_id', $user->branch_id)->pluck('id')->toArray();
        }
        
        if ($user->hasRole('Store Manager')) {
            return [$user->store_id];
        }
        
        return [];
    }

    private function getTotalSales($fromDate, $toDate, $user)
    {
        return Sale::whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $storeIds = Store::where('branch_id', $user->branch_id)->pluck('id');
                    $query->whereIn('store_id', $storeIds);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('store_id', $user->store_id);
                }
            })
            ->count();
    }

    private function getTotalMovements($fromDate, $toDate, $user)
    {
        return InventoryMovement::whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                $accessibleStoreIds = $this->getAccessibleStoreIds($user);
                $query->where(function ($q) use ($accessibleStoreIds) {
                    $q->whereIn('from_store_id', $accessibleStoreIds)
                      ->orWhereIn('to_store_id', $accessibleStoreIds);
                });
            })
            ->count();
    }

    private function getLowStockCount($user)
    {
        return DB::table('inventories')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->whereRaw('inventories.quantity - inventories.reserved_quantity <= inventories.reorder_point')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('stores.branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('inventories.store_id', $user->store_id);
                }
            })
            ->count();
    }

    private function getInventoryValue($user)
    {
        return DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('stores.branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('inventories.store_id', $user->store_id);
                }
            })
            ->sum(DB::raw('inventories.quantity * products.selling_price'));
    }

    private function getSalesOverview($fromDate, $toDate, $user)
    {
        $query = DB::table('sales')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(grand_total) as total')
            )
            ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->groupBy(DB::raw('DATE(created_at)'));

        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $storeIds = Store::where('branch_id', $user->branch_id)->pluck('id');
                $query->whereIn('store_id', $storeIds);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('store_id', $user->store_id);
            }
        }

        return $query->orderBy('date')->get();
    }

    private function getTopProducts($fromDate, $toDate, $user, $limit)
    {
        $query = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total) as total_revenue')
            )
            ->whereBetween('sales.created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->groupBy('products.id', 'products.name', 'products.sku');

        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $storeIds = Store::where('branch_id', $user->branch_id)->pluck('id');
                $query->whereIn('sales.store_id', $storeIds);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('sales.store_id', $user->store_id);
            }
        }

        return $query->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }

    private function getMovementTypesBreakdown($fromDate, $toDate, $user)
    {
        $query = InventoryMovement::select(
                'movement_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_quantity')
            )
            ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->groupBy('movement_type');

        if (!$user->hasRole('Administrator')) {
            $accessibleStoreIds = $this->getAccessibleStoreIds($user);
            $query->where(function ($q) use ($accessibleStoreIds) {
                $q->whereIn('from_store_id', $accessibleStoreIds)
                  ->orWhereIn('to_store_id', $accessibleStoreIds);
            });
        }

        return $query->get();
    }

    private function getStorePerformance($fromDate, $toDate, $user, $limit)
    {
        $query = DB::table('sales')
            ->join('stores', 'sales.store_id', '=', 'stores.id')
            ->select(
                'stores.id',
                'stores.name',
                'stores.code',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(grand_total) as total_revenue'),
                DB::raw('AVG(grand_total) as average_sale_value')
            )
            ->whereBetween('sales.created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->groupBy('stores.id', 'stores.name', 'stores.code');

        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $query->where('stores.branch_id', $user->branch_id);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('sales.store_id', $user->store_id);
            }
        }

        return $query->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();
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
                'inventories.reserved_quantity',
                DB::raw('inventories.quantity - inventories.reserved_quantity as available'),
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
                    'reference' => $movement->reference_number,
                    'type' => $movement->movement_type,
                    'product' => $movement->product->name,
                    'sku' => $movement->product->sku,
                    'quantity' => $movement->quantity,
                    'from_store' => $movement->fromStore->name ?? 'N/A',
                    'to_store' => $movement->toStore->name ?? 'N/A',
                    'created_by' => $movement->creator->name ?? 'System',
                ];
            });
    }

    private function getSalesData($request)
    {
        return DB::table('sales')
            ->join('stores', 'sales.store_id', '=', 'stores.id')
            ->join('users', 'sales.created_by', '=', 'users.id')
            ->select(
                'sales.sale_number',
                'stores.name as store',
                'sales.created_at as date',
                'sales.grand_total as amount',
                'sales.payment_status',
                'sales.status',
                'users.name as cashier'
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
                'category',
                'selling_price as price',
                'cost_price as cost',
                DB::raw('selling_price - cost_price as profit_margin'),
                DB::raw('ROUND(((selling_price - cost_price) / selling_price) * 100, 2) as margin_percentage'),
                'unit',
                'reorder_level',
                'is_active'
            )
            ->where('is_active', true)
            ->get();
    }

    private function getLowStockData($request)
    {
        $user = auth()->user();
        
        return DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->select(
                'stores.name as store',
                'products.sku',
                'products.name as product',
                'inventories.quantity',
                'inventories.reserved_quantity',
                DB::raw('inventories.quantity - inventories.reserved_quantity as available'),
                'inventories.reorder_point',
                DB::raw('(inventories.quantity - inventories.reserved_quantity) * products.selling_price as value_at_risk')
            )
            ->whereRaw('inventories.quantity - inventories.reserved_quantity <= inventories.reorder_point')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('stores.branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('inventories.store_id', $user->store_id);
                }
            })
            ->orderBy('available', 'asc')
            ->get();
    }
}