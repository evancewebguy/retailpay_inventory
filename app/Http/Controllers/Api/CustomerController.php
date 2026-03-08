<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CustomerController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            // new Middleware('auth:sanctum'),
            // new Middleware('permission:view customers', only: ['index', 'show', 'search']),
            // new Middleware('permission:create customers', only: ['store']),
            // new Middleware('permission:edit customers', only: ['update']),
            // new Middleware('permission:delete customers', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of customers.
     */
    public function index(Request $request)
    {
        $customers = Customer::query()
            ->when($request->search, function ($query, $search) {
                return $query->search($search);
            })
            ->orderBy($request->sort_by ?? 'name', $request->sort_direction ?? 'asc')
            ->paginate($request->per_page ?? 20)
            ->withQueryString();

        return response()->json([
            'data' => $customers->items(),
            'current_page' => $customers->currentPage(),
            'per_page' => $customers->perPage(),
            'total' => $customers->total(),
            'last_page' => $customers->lastPage(),
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        $customer = Customer::create($validated);

        return response()->json([
            'message' => 'Customer created successfully',
            'data' => $customer,
        ], 201);
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer)
    {
        return response()->json([
            'data' => $customer->load(['sales' => function ($query) {
                $query->latest()->limit(10);
            }]),
        ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255|unique:customers,email,' . $customer->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer,
        ]);
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer)
    {
        // Check if customer has any sales
        if ($customer->sales()->exists()) {
            return response()->json([
                'message' => 'Cannot delete customer with existing sales.'
            ], 400);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }

    /**
     * Get customers as a simple list (for dropdowns).
     */
    public function list(Request $request)
    {
        $customers = Customer::select('id', 'name', 'email', 'phone')
            ->when($request->search, function ($query, $search) {
                return $query->search($search);
            })
            ->orderBy('name')
            ->limit($request->limit ?? 50)
            ->get();

        return response()->json($customers);
    }

    /**
     * Search customers (for autocomplete).
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $customers = Customer::select('id', 'name', 'email', 'phone')
            ->search($request->query)
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($customers);
    }

    /**
     * Get customer sales history.
     */
    public function salesHistory(Customer $customer, Request $request)
    {
        $sales = $customer->sales()
            ->with(['store', 'items.product'])
            ->when($request->from_date, function ($query, $date) {
                return $query->whereDate('created_at', '>=', $date);
            })
            ->when($request->to_date, function ($query, $date) {
                return $query->whereDate('created_at', '<=', $date);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $sales->items(),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
            'customer' => $customer->only(['id', 'name', 'email', 'phone']),
        ]);
    }

    /**
     * Get customer statistics.
     */
    public function statistics(Customer $customer)
    {
        $totalSales = $customer->sales()->count();
        $totalSpent = $customer->sales()->sum('grand_total');
        $averageOrderValue = $totalSales > 0 ? $totalSpent / $totalSales : 0;
        $lastSale = $customer->sales()->latest()->first();

        return response()->json([
            'total_sales' => $totalSales,
            'total_spent' => $totalSpent,
            'average_order_value' => round($averageOrderValue, 2),
            'last_sale_date' => $lastSale?->created_at,
            'last_sale_amount' => $lastSale?->grand_total,
        ]);
    }
}