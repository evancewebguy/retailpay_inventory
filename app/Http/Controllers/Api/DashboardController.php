<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Sale;
use App\Models\Transfer;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    /**
     * Display the dashboard page.
     */
    // public function index()
    // {
    //     return Inertia::render('Dashboard');
    // }

    /**
     * Get dashboard statistics for API.
     */
    public function getStats(Request $request)
    {
        $user = auth()->user();
        
        // Today's sales
        $todaySales = Sale::whereDate('created_at', today())
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->whereHas('store', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('store_id', $user->store_id);
                }
            })
            ->sum('grand_total');

        // Pending transfers
        $pendingTransfers = Transfer::where('status', 'PENDING')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where(function ($q) use ($user) {
                        $q->whereHas('fromStore', function ($sq) use ($user) {
                            $sq->where('branch_id', $user->branch_id);
                        })->orWhereHas('toStore', function ($sq) use ($user) {
                            $sq->where('branch_id', $user->branch_id);
                        });
                    });
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where(function ($q) use ($user) {
                        $q->where('from_store_id', $user->store_id)
                          ->orWhere('to_store_id', $user->store_id);
                    });
                }
            })
            ->count();

        // Low stock count
        $lowStockCount = Inventory::whereRaw('quantity - reserved_quantity <= reorder_point')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->whereHas('store', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('store_id', $user->store_id);
                }
            })
            ->count();

        // Total inventory value
        $inventoryValue = Inventory::with('product')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->whereHas('store', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('store_id', $user->store_id);
                }
            })
            ->get()
            ->sum(function ($inventory) {
                return $inventory->quantity * ($inventory->product->selling_price ?? 0);
            });

        // Recent sales
        $recentSales = Sale::with(['store', 'items'])
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->whereHas('store', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('store_id', $user->store_id);
                }
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                    'grand_total' => $sale->grand_total,
                    'created_at' => $sale->created_at->toISOString(),
                ];
            });

        // Low stock items
        $lowStockItems = Inventory::with(['product', 'store'])
            ->whereRaw('quantity - reserved_quantity <= reorder_point')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->whereHas('store', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('store_id', $user->store_id);
                }
            })
            ->limit(5)
            ->get()
            ->map(function ($inventory) {
                return [
                    'id' => $inventory->id,
                    'product_name' => $inventory->product->name,
                    'store_name' => $inventory->store->name,
                    'quantity' => $inventory->quantity - $inventory->reserved_quantity,
                    'reorder_point' => $inventory->reorder_point,
                ];
            });

        return response()->json([
            'todaySales' => $todaySales,
            'pendingTransfers' => $pendingTransfers,
            'lowStockCount' => $lowStockCount,
            'inventoryValue' => $inventoryValue,
            'recentSales' => $recentSales,
            'lowStockItems' => $lowStockItems,
        ]);
    }

    /**
     * Get sales chart data.
     */
    public function getSalesChart(Request $request)
    {
        $user = auth()->user();
        $days = $request->get('days', 7);
        
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $sales = Sale::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(grand_total) as total')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->whereHas('store', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('store_id', $user->store_id);
                }
            })
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill in missing dates
        $chartData = [];
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($days - 1 - $i)->format('Y-m-d');
            $dayData = $sales->firstWhere('date', $date);
            
            $chartData[] = [
                'date' => $date,
                'count' => $dayData ? $dayData->count : 0,
                'total' => $dayData ? (float) $dayData->total : 0,
            ];
        }

        return response()->json($chartData);
    }

    /**
     * Get top products.
     */
    public function getTopProducts(Request $request)
    {
        $user = auth()->user();
        $limit = $request->get('limit', 5);

        $topProducts = DB::table('sale_items')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total) as total_revenue')
            )
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->whereHas('sales.store', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('sales.store_id', $user->store_id);
                }
            })
            ->where('sales.created_at', '>=', now()->subDays(30))
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        return response()->json($topProducts);
    }

    /**
     * Get recent activities.
     */
    public function getRecentActivities()
    {
        $user = auth()->user();
        
        // Get recent sales
        $recentSales = Sale::with(['store', 'creator'])
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->whereHas('store', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('store_id', $user->store_id);
                }
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'type' => 'sale',
                    'description' => "New sale #{$sale->sale_number} for \${$sale->grand_total}",
                    'time' => $sale->created_at->diffForHumans(),
                    'user' => $sale->creator->name,
                ];
            });

        // Get recent transfers
        $recentTransfers = Transfer::with(['fromStore', 'toStore', 'creator'])
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where(function ($q) use ($user) {
                        $q->whereHas('fromStore', function ($sq) use ($user) {
                            $sq->where('branch_id', $user->branch_id);
                        })->orWhereHas('toStore', function ($sq) use ($user) {
                            $sq->where('branch_id', $user->branch_id);
                        });
                    });
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where(function ($q) use ($user) {
                        $q->where('from_store_id', $user->store_id)
                          ->orWhere('to_store_id', $user->store_id);
                    });
                }
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($transfer) {
                return [
                    'id' => $transfer->id,
                    'type' => 'transfer',
                    'description' => "Transfer #{$transfer->transfer_number} from {$transfer->fromStore->name} to {$transfer->toStore->name}",
                    'time' => $transfer->created_at->diffForHumans(),
                    'user' => $transfer->creator->name,
                ];
            });

        // Combine and sort by date
        $activities = $recentSales->concat($recentTransfers)
            ->sortByDesc('time')
            ->values()
            ->take(10);

        return response()->json($activities);
    }
}