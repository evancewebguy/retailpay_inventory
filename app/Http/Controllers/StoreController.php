<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;

class StoreController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
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
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
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
            ->orderBy($request->sort_by ?? 'name')
            ->paginate($request->per_page ?? 20)
            ->withQueryString();

        return Inertia::render('Stores/Index', [
            'stores' => $stores,
            'filters' => $request->only(['branch_id', 'search', 'sort_by', 'sort_direction']),
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
                return redirect()->route('stores.index')
                    ->with('error', 'Unauthorized to view this store');
            }
            
            if ($user->hasRole('Store Manager') && $store->id !== $user->store_id) {
                return redirect()->route('stores.index')
                    ->with('error', 'Unauthorized to view this store');
            }
        }

        return Inertia::render('Stores/Show', [
            'store' => $store->load('branch'),
        ]);
    }

    /**
     * Get all stores as a simple list (for dropdowns).
     */
    public function list(Request $request)
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
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json($stores);
    }
}