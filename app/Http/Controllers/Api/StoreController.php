<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class StoreController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth:sanctum'),
            // new Middleware('permission:view stores', only: ['index', 'show']),
        ];
    }

    /**
     * Display a listing of all stores.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $stores = Store::with('branch')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    // Branch managers see only stores in their branch
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    // Store managers see only their own store
                    $query->where('id', $user->store_id);
                }
            })
            ->when($request->branch_id, function ($query, $branchId) {
                return $query->where('branch_id', $branchId);
            })
            ->when($request->search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->when($request->active === 'true' || $request->active === 'false', function ($query) use ($request) {
                return $query->where('is_active', $request->active === 'true');
            })
            ->orderBy($request->sort_by ?? 'name', $request->sort_direction ?? 'asc')
            ->paginate($request->per_page ?? 20)
            ->withQueryString();

        return response()->json([
            'data' => $stores->items(),
            'current_page' => $stores->currentPage(),
            'per_page' => $stores->perPage(),
            'total' => $stores->total(),
            'last_page' => $stores->lastPage(),
        ]);
    }

    /**
     * Display the specified store.
     */
    public function show(Store $store)
    {
        $user = auth()->user();

        // Check if user has access to this store
        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager') && $store->branch_id !== $user->branch_id) {
                return response()->json([
                    'message' => 'Unauthorized to view this store'
                ], 403);
            }
            
            if ($user->hasRole('Store Manager') && $store->id !== $user->store_id) {
                return response()->json([
                    'message' => 'Unauthorized to view this store'
                ], 403);
            }
        }

        return response()->json(
            $store->load('branch')
        );
    }

    /**
     * Get all stores as a simple list (for dropdowns).
     */
    public function list(Request $request)
    {
        $user = auth()->user();
        
        $stores = Store::select('id', 'name', 'code', 'branch_id')
            ->with('branch:id,name')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json($stores);
    }
}

