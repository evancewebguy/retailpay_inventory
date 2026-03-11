<?php

namespace App\Http\Controllers;

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
use Inertia\Inertia;

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
            new Middleware('auth'),
            new Middleware('permission:view reports'),
        ];
    }

    /**
     * Display reports dashboard.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Get date range (default to last 30 days)
        $toDate = $request->to_date ?? now()->toDateString();
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();

        // Get summary data
        $summary = $this->getSummaryData($fromDate, $toDate, $user);
        $salesOverview = $this->getSalesOverviewData($fromDate, $toDate, $user);
        $topProducts = $this->getTopProductsData($fromDate, $toDate, $user, 5);
        $movementTypes = $this->getMovementTypesData($fromDate, $toDate, $user);
        $recentMovements = $this->getRecentMovementsData($fromDate, $toDate, $user, 10);
        $storePerformance = $this->getStorePerformanceData($fromDate, $toDate, $user, 5);

        // Get stores for filter dropdown
        $stores = Store::select('id', 'name', 'code')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->get();

        return Inertia::render('Report/Index', [
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'store_id' => $request->store_id,
            ],
            'stores' => $stores,
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
     * Stock valuation report page
     */
    public function stockValuation(Request $request)
    {
        $user = auth()->user();
        
        $branches = DB::table('branches')->select('id', 'name')->get();
        
        $stores = Store::select('id', 'name', 'code', 'branch_id')
            ->with('branch:id,name')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->get();

    
        // Get stock valuation data
        $data = $this->getStockValuationData($request, $user);

        return Inertia::render('Report/StockValuation', [
            'filters' => $request->only(['branch_id', 'store_id', 'per_page']),
            'branches' => $branches,
            'stores' => $stores,
            'report_data' => $data,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Movement history report page
     */
    public function movementHistory(Request $request)
    {
        $user = auth()->user();
        
        $stores = Store::select('id', 'name', 'code')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->get();

        $products = Product::select('id', 'name', 'sku')->get();

        $movementTypes = [
            ['value' => 'SALE', 'label' => 'Sale'],
            ['value' => 'TRANSFER', 'label' => 'Transfer'],
            ['value' => 'ADJUSTMENT', 'label' => 'Adjustment'],
            ['value' => 'PROCUREMENT', 'label' => 'Procurement'],
            ['value' => 'RETURN', 'label' => 'Return'],
            ['value' => 'DAMAGE', 'label' => 'Damage'],
            ['value' => 'LOST', 'label' => 'Lost'],
        ];

        // Set default dates
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        // Get movement history data
        $data = $this->getMovementHistoryData($request, $user, $fromDate, $toDate);

        return Inertia::render('Report/MovementHistory', [
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'store_id' => $request->store_id,
                'product_id' => $request->product_id,
                'movement_type' => $request->movement_type,
                'per_page' => $request->per_page ?? 50,
                'page' => $request->page ?? 1,
            ],
            'stores' => $stores,
            'products' => $products,
            'movement_types' => $movementTypes,
            'report_data' => $data,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Sales report page
     */
    public function salesReport(Request $request)
    {
        $user = auth()->user();
        
        $stores = Store::select('id', 'name', 'code')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->get();

        // Set default values
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();
        $interval = $request->interval ?? 'daily';

        // Get sales report data
        $data = $this->getSalesReportData($request, $user, $fromDate, $toDate, $interval);

        return Inertia::render('Report/SalesReport', [
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'store_id' => $request->store_id,
                'interval' => $interval,
            ],
            'stores' => $stores,
            'report_data' => $data,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Product performance report page
     */
    public function productPerformance(Request $request)
    {
        $user = auth()->user();
        
        $stores = Store::select('id', 'name', 'code')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->get();


        // Set default values
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();
        $limit = $request->limit ?? 20;

        // Get product performance data
        $data = $this->getProductPerformanceData($request, $user, $fromDate, $toDate, $limit);

        return Inertia::render('Report/ProductPerformance', [
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'store_id' => $request->store_id,
                'limit' => $limit,
            ],
            'stores' => $stores,
            'report_data' => $data,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Low stock report page
     */
    public function lowStock(Request $request)
    {
        $user = auth()->user();
        
        $stores = Store::select('id', 'name', 'code')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->get();

        // Set default values
        $threshold = $request->threshold ?? 0;
        $perPage = $request->per_page ?? 50;

        // Get low stock data
        $data = $this->getLowStockData($request, $user, $threshold, $perPage);

        return Inertia::render('Report/LowStock', [
            'filters' => [
                'store_id' => $request->store_id,
                'threshold' => $threshold,
                'per_page' => $perPage,
                'page' => $request->page ?? 1,
            ],
            'stores' => $stores,
            'report_data' => $data,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Export report as CSV
     */
    public function export(Request $request)
    {
        $request->validate([
            'type' => 'required|in:valuation,movements,sales,products,low-stock',
        ]);

        $user = auth()->user();

        switch ($request->type) {
            case 'valuation':
                return $this->exportValuation($request, $user);
            case 'movements':
                return $this->exportMovements($request, $user);
            case 'sales':
                return $this->exportSales($request, $user);
            case 'products':
                return $this->exportProducts($request, $user);
            case 'low-stock':
                return $this->exportLowStock($request, $user);
            default:
                return redirect()->back()->with('error', 'Invalid export type');
        }
    }

    // ==================== DATA METHODS ====================

    private function getSummaryData($fromDate, $toDate, $user, $storeId = null)
    {
        return [
            'total_sales' => $this->getTotalSales($fromDate, $toDate, $user, $storeId),
            'total_movements' => $this->getTotalMovements($fromDate, $toDate, $user, $storeId),
            'total_products' => Product::count(),
            'total_stores' => Store::count(),
            'low_stock_count' => $this->getLowStockCount($user, $storeId),
            'inventory_value' => $this->getInventoryValue($user, $storeId),
        ];
    }

    private function getSalesOverviewData($fromDate, $toDate, $user, $storeId = null)
    {
        $query = DB::table('sales')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(grand_total) as total')
            )
            ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->groupBy(DB::raw('DATE(created_at)'));

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

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

    private function getTopProductsData($fromDate, $toDate, $user, $limit, $storeId = null)
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

        if ($storeId) {
            $query->where('sales.store_id', $storeId);
        }

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

    private function getMovementTypesData($fromDate, $toDate, $user, $storeId = null)
    {
        $query = InventoryMovement::select(
                'movement_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_quantity')
            )
            ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->groupBy('movement_type');

        if ($storeId) {
            $query->where(function ($q) use ($storeId) {
                $q->where('from_store_id', $storeId)
                  ->orWhere('to_store_id', $storeId);
            });
        }

        if (!$user->hasRole('Administrator')) {
            $accessibleStoreIds = $this->getAccessibleStoreIds($user);
            $query->where(function ($q) use ($accessibleStoreIds) {
                $q->whereIn('from_store_id', $accessibleStoreIds)
                  ->orWhereIn('to_store_id', $accessibleStoreIds);
            });
        }

        return $query->get();
    }

    private function getRecentMovementsData($fromDate, $toDate, $user, $limit = 10, $storeId = null)
    {
        $query = InventoryMovement::with(['product', 'fromStore', 'toStore', 'creator'])
            ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);

        if ($storeId) {
            $query->where(function ($q) use ($storeId) {
                $q->where('from_store_id', $storeId)
                  ->orWhere('to_store_id', $storeId);
            });
        }

        if (!$user->hasRole('Administrator')) {
            $accessibleStoreIds = $this->getAccessibleStoreIds($user);
            $query->where(function ($q) use ($accessibleStoreIds) {
                $q->whereIn('from_store_id', $accessibleStoreIds)
                  ->orWhereIn('to_store_id', $accessibleStoreIds);
            });
        }

        return $query->latest()
            ->limit($limit)
            ->get()
            ->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'reference' => $movement->reference_number,
                    'type' => $movement->movement_type,
                    'product'  => $movement->product ? [
                        'id' => $movement->product->id,
                        'name' => $movement->product->name,
                        'sku' => $movement->product->sku,
                    ] : null,
                    'quantity' => $movement->quantity,
                    'from_store' => $movement->fromStore ? [
                        'id' => $movement->fromStore->id,
                        'name' => $movement->fromStore->name,
                        'code' => $movement->fromStore->code,
                    ] : null,
                    'to_store' => $movement->toStore ? [
                        'id' => $movement->toStore->id,
                        'name' => $movement->toStore->name,
                        'code' => $movement->toStore->code,
                    ] : null,
                    'created_at' => $movement->created_at->toDateTimeString(),
                    'created_by' => $movement->creator->name ?? 'System',
                ];
            });
    }

    private function getStorePerformanceData($fromDate, $toDate, $user, $limit, $storeId = null)
    {
        $query = DB::table('sales')
            ->join('stores', 'sales.store_id', '=', 'stores.id')
            ->select(
                'stores.id',
                'stores.name',
                'stores.code',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(sales.grand_total) as total_revenue'),
                DB::raw('AVG(sales.grand_total) as average_sale_value')
            )
            ->whereBetween('sales.created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->groupBy('stores.id', 'stores.name', 'stores.code');

        if ($storeId) {
            $query->where('sales.store_id', $storeId);
        }

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

    private function getStockValuationData(Request $request, $user)
    {
        $query = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->join('branches', 'stores.branch_id', '=', 'branches.id')
            ->select(
                'branches.id as branch_id',
                'branches.name as branch_name',
                'stores.id as store_id',
                'stores.name as store_name',
                'products.id as product_id',
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


        // Restrict by user permissions
        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $query->where('stores.branch_id', $user->branch_id);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('inventories.store_id', $user->store_id);
            }
        }

        $perPage = $request->per_page ?? 20;
        $paginator = $query->paginate($perPage);

        // Calculate totals
        $totals = [
            'total_cost' => $paginator->sum('total_cost'),
            'total_value' => $paginator->sum('total_value'),
            'available_value' => $paginator->sum('available_value'),
            'potential_profit' => $paginator->sum('total_value') - $paginator->sum('total_cost'),
            'total_items' => $paginator->total(),
            'total_quantity' => $paginator->sum('quantity'),
        ];

        // Group by branch
        $byBranch = collect($paginator->items())->groupBy('branch_name')->map(function ($items) {
            return [
                'total_value' => $items->sum('total_value'),
                'total_cost' => $items->sum('total_cost'),
                'item_count' => $items->count(),
            ];
        });

        return [
            'data' => $paginator->items(),
            'totals' => $totals,
            'by_branch' => $byBranch,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total()
            ]
        ];
    }

    private function getMovementHistoryData(Request $request, $user, $fromDate, $toDate)
    {
        $query = InventoryMovement::with(['product', 'fromStore', 'toStore', 'creator'])
            ->whereBetween('created_at', [
                $fromDate . ' 00:00:00',
                $toDate . ' 23:59:59'
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

        if (!$user->hasRole('Administrator')) {
            $accessibleStoreIds = $this->getAccessibleStoreIds($user);
            $query->where(function ($q) use ($accessibleStoreIds) {
                $q->whereIn('from_store_id', $accessibleStoreIds)
                  ->orWhereIn('to_store_id', $accessibleStoreIds);
            });
        }

        $perPage = $request->per_page ?? 50;
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Calculate summary
        $summary = [
            'total_movements' => $paginator->total(),
            'total_quantity' => abs($paginator->sum('quantity')),
            'by_type' => $paginator->groupBy('movement_type')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_quantity' => abs($group->sum('quantity'))
                    ];
                })
        ];

        return [
            'data' => $paginator->items(),
            'summary' => $summary,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total()
            ]
        ];
    }

    private function getSalesReportData(Request $request, $user, $fromDate, $toDate, $interval)
    {
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
                $fromDate . ' 00:00:00',
                $toDate . ' 23:59:59'
            ]);

        if ($request->store_id) {
            $query->where('sales.store_id', $request->store_id);
        }

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

        $totals = [
            'total_sales' => $results->sum('total_sales'),
            'total_items_sold' => $results->sum('total_items_sold'),
            'total_revenue' => $results->sum('total_revenue'),
            'total_discount' => $results->sum('total_discount'),
            'total_profit' => $results->sum('total_profit'),
            'average_sale_value' => $results->sum('total_sales') > 0 
                ? round($results->sum('total_revenue') / $results->sum('total_sales'), 2) 
                : 0,
            'average_items_per_sale' => $results->sum('total_sales') > 0
                ? round($results->sum('total_items_sold') / $results->sum('total_sales'), 2)
                : 0,
        ];

        return [
            'interval' => $interval,
            'data' => $results,
            'totals' => $totals,
        ];
    }

    private function getProductPerformanceData(Request $request, $user, $fromDate, $toDate, $limit)
    {
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
                DB::raw('SUM(sale_items.quantity * products.cost_price) as total_cost'),
                DB::raw('SUM(sale_items.total) - SUM(sale_items.quantity * products.cost_price) as total_profit'),
                DB::raw('AVG(sale_items.quantity) as average_quantity_per_sale')
            )
            ->whereBetween('sales.created_at', [
                $fromDate . ' 00:00:00',
                $toDate . ' 23:59:59'
            ]);

        if ($request->store_id) {
            $query->where('sales.store_id', $request->store_id);
        }

        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $storeIds = Store::where('branch_id', $user->branch_id)->pluck('id');
                $query->whereIn('sales.store_id', $storeIds);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('sales.store_id', $user->store_id);
            }
        }

        $results = $query->groupBy('products.id', 'products.sku', 'products.name')
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

        $totals = [
            'total_revenue' => $results->sum('total_revenue'),
            'total_profit' => $results->sum('total_profit'),
            'total_quantity' => $results->sum('total_quantity_sold'),
            'average_margin' => round($results->avg('profit_margin'), 2),
        ];

        return [
            'data' => $results,
            'totals' => $totals,
        ];
    }

    private function getLowStockData(Request $request, $user, $threshold, $perPage)
    {
        $query = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->select(
                'stores.id as store_id',
                'stores.name as store_name',
                'stores.branch_id',
                'products.id as product_id',
                'products.sku',
                'products.name as product_name',
                'inventories.quantity',
                'inventories.reserved_quantity',
                DB::raw('inventories.quantity - inventories.reserved_quantity as available_quantity'),
                'inventories.reorder_point',
                'products.cost_price',
                'products.selling_price',
                DB::raw('(inventories.quantity - inventories.reserved_quantity) * products.selling_price as value_at_risk')
            )
            ->whereRaw('inventories.quantity - inventories.reserved_quantity <= GREATEST(inventories.reorder_point, ?)', [$threshold]);

        if ($request->store_id) {
            $query->where('inventories.store_id', $request->store_id);
        }

        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $query->where('stores.branch_id', $user->branch_id);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('inventories.store_id', $user->store_id);
            }
        }

        $paginator = $query->orderBy('available_quantity', 'asc')
            ->paginate($perPage);

        $totalValueAtRisk = collect($paginator->items())->sum('value_at_risk');

        return [
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total()
            ],
            'total_value_at_risk' => $totalValueAtRisk,
        ];
    }

    // ==================== HELPER METHODS ====================

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

    private function getTotalSales($fromDate, $toDate, $user, $storeId = null)
    {
        return Sale::whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
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

    private function getTotalMovements($fromDate, $toDate, $user, $storeId = null)
    {
        return InventoryMovement::whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->when($storeId, function ($query) use ($storeId) {
                $query->where(function ($q) use ($storeId) {
                    $q->where('from_store_id', $storeId)
                      ->orWhere('to_store_id', $storeId);
                });
            })
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                $accessibleStoreIds = $this->getAccessibleStoreIds($user);
                $query->where(function ($q) use ($accessibleStoreIds) {
                    $q->whereIn('from_store_id', $accessibleStoreIds)
                      ->orWhereIn('to_store_id', $accessibleStoreIds);
                });
            })
            ->count();
    }

    private function getLowStockCount($user, $storeId = null)
    {
        return DB::table('inventories')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->whereRaw('inventories.quantity - inventories.reserved_quantity <= inventories.reorder_point')
            ->when($storeId, fn($q) => $q->where('inventories.store_id', $storeId))
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('stores.branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('inventories.store_id', $user->store_id);
                }
            })
            ->count();
    }

    private function getInventoryValue($user, $storeId = null)
    {
        return DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->when($storeId, fn($q) => $q->where('inventories.store_id', $storeId))
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('stores.branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('inventories.store_id', $user->store_id);
                }
            })
            ->sum(DB::raw('inventories.quantity * products.selling_price'));
    }

    // ==================== EXPORT METHODS ====================

    private function exportValuation(Request $request, $user)
    {
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
                DB::raw('inventories.quantity * products.selling_price as total_value')
            );

        if ($request->branch_id) {
            $query->where('stores.branch_id', $request->branch_id);
        }

        if ($request->store_id) {
            $query->where('inventories.store_id', $request->store_id);
        }

        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $query->where('stores.branch_id', $user->branch_id);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('inventories.store_id', $user->store_id);
            }
        }

        $data = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="stock_valuation_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Branch', 'Store', 'SKU', 'Product',
                'Quantity', 'Reserved', 'Available',
                'Cost Price', 'Selling Price', 'Total Cost', 'Total Value'
            ]);
            
            foreach ($data as $row) {
                fputcsv($file, [
                    $row->branch_name,
                    $row->store_name,
                    $row->sku,
                    $row->product_name,
                    $row->quantity,
                    $row->reserved_quantity,
                    $row->available_quantity,
                    $row->cost_price,
                    $row->selling_price,
                    $row->total_cost,
                    $row->total_value,
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportMovements(Request $request, $user)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $query = InventoryMovement::with(['product', 'fromStore', 'toStore', 'creator'])
            ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);

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

        if (!$user->hasRole('Administrator')) {
            $accessibleStoreIds = $this->getAccessibleStoreIds($user);
            $query->where(function ($q) use ($accessibleStoreIds) {
                $q->whereIn('from_store_id', $accessibleStoreIds)
                  ->orWhereIn('to_store_id', $accessibleStoreIds);
            });
        }

        $data = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="movement_history_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Date', 'Reference', 'Type', 'Product', 'SKU',
                'Quantity', 'From Store', 'To Store', 'Created By'
            ]);
            
            foreach ($data as $movement) {
                fputcsv($file, [
                    $movement->created_at->toDateTimeString(),
                    $movement->reference_number,
                    $movement->movement_type,
                    $movement->product->name,
                    $movement->product->sku,
                    $movement->quantity,
                    $movement->fromStore->name ?? 'N/A',
                    $movement->toStore->name ?? 'N/A',
                    $movement->creator->name ?? 'System',
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportSales(Request $request, $user)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();
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
            ->whereBetween('sales.created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);

        if ($request->store_id) {
            $query->where('sales.store_id', $request->store_id);
        }

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

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales_report_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        $callback = function () use ($results) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Period', 'Total Sales', 'Items Sold', 'Revenue', 'Discount', 'Profit'
            ]);
            
            foreach ($results as $row) {
                fputcsv($file, [
                    $row->period,
                    $row->total_sales,
                    $row->total_items_sold,
                    $row->total_revenue,
                    $row->total_discount,
                    $row->total_profit,
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportProducts(Request $request, $user)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $query = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->select(
                'products.sku',
                'products.name as product_name',
                DB::raw('SUM(sale_items.quantity) as total_quantity_sold'),
                DB::raw('COUNT(DISTINCT sales.id) as number_of_sales'),
                DB::raw('SUM(sale_items.total) as total_revenue'),
                DB::raw('SUM(sale_items.quantity * products.cost_price) as total_cost'),
                DB::raw('SUM(sale_items.total) - SUM(sale_items.quantity * products.cost_price) as total_profit')
            )
            ->whereBetween('sales.created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);

        if ($request->store_id) {
            $query->where('sales.store_id', $request->store_id);
        }

        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $storeIds = Store::where('branch_id', $user->branch_id)->pluck('id');
                $query->whereIn('sales.store_id', $storeIds);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('sales.store_id', $user->store_id);
            }
        }

        $results = $query->groupBy('products.id', 'products.sku', 'products.name')
            ->orderBy('total_quantity_sold', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="product_performance_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        $callback = function () use ($results) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'SKU', 'Product', 'Quantity Sold', 'Number of Sales',
                'Revenue', 'Cost', 'Profit'
            ]);
            
            foreach ($results as $row) {
                $profit = $row->total_revenue - $row->total_cost;
                fputcsv($file, [
                    $row->sku,
                    $row->product_name,
                    $row->total_quantity_sold,
                    $row->number_of_sales,
                    $row->total_revenue,
                    $row->total_cost,
                    $profit,
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportLowStock(Request $request, $user)
    {
        $threshold = $request->threshold ?? 0;

        $query = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->select(
                'stores.name as store_name',
                'products.sku',
                'products.name as product_name',
                'inventories.quantity',
                'inventories.reserved_quantity',
                DB::raw('inventories.quantity - inventories.reserved_quantity as available_quantity'),
                'inventories.reorder_point',
                'products.cost_price',
                'products.selling_price'
            )
            ->whereRaw('inventories.quantity - inventories.reserved_quantity <= GREATEST(inventories.reorder_point, ?)', [$threshold]);

        if ($request->store_id) {
            $query->where('inventories.store_id', $request->store_id);
        }

        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $query->where('stores.branch_id', $user->branch_id);
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('inventories.store_id', $user->store_id);
            }
        }

        $data = $query->orderBy('available_quantity', 'asc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="low_stock_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Store', 'SKU', 'Product', 
                'On Hand', 'Reserved', 'Available',
                'Reorder Point', 'Cost Price', 'Selling Price'
            ]);
            
            foreach ($data as $row) {
                fputcsv($file, [
                    $row->store_name,
                    $row->sku,
                    $row->product_name,
                    $row->quantity,
                    $row->reserved_quantity,
                    $row->available_quantity,
                    $row->reorder_point,
                    $row->cost_price,
                    $row->selling_price,
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}