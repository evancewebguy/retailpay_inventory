<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\InventoryService;
use App\Http\Requests\TransferRequest;
use App\Models\Transfer;
use App\Models\Store;
use App\Models\Product;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class TransferController extends Controller implements HasMiddleware
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
            new Middleware('permission:view transfers', only: ['index', 'show']),
            new Middleware('permission:create transfers', only: ['create', 'store']),
            new Middleware('permission:approve transfers', only: ['approve']),
            new Middleware('permission:receive transfers', only: ['receive']),
        ];
    }

    /**
     * Display a listing of transfers.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = Transfer::with(['fromStore', 'toStore', 'items.product', 'creator', 'approver'])
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->from_store_id, function ($query, $storeId) {
                return $query->where('from_store_id', $storeId);
            })
            ->when($request->to_store_id, function ($query, $storeId) {
                return $query->where('to_store_id', $storeId);
            })
            ->when($request->search, function ($query, $search) {
                return $query->where('transfer_number', 'like', "%{$search}%");
            });

        // Filter by user's accessible stores
        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $query->where(function ($q) use ($user) {
                    $q->whereHas('fromStore', fn($sq) => $sq->where('branch_id', $user->branch_id))
                      ->orWhereHas('toStore', fn($sq) => $sq->where('branch_id', $user->branch_id));
                });
            } elseif ($user->hasRole('Store Manager')) {
                $query->where(function ($q) use ($user) {
                    $q->where('from_store_id', $user->store_id)
                      ->orWhere('to_store_id', $user->store_id);
                });
            }
        }

        $transfers = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20)
            ->withQueryString();

        // Get filter data
        $stores = Store::select('id', 'name', 'code')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->get();

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
            'stores' => $stores,
            'filters' => $request->only(['status', 'from_store_id', 'to_store_id', 'search', 'per_page']),
        ]);
    }

    /**
     * Show form for creating a new transfer.
     */
    public function create(Request $request)
    {
        $user = auth()->user();
        
        // Get stores user has access to for source
        $sourceStores = Store::select('id', 'name', 'code', 'branch_id')
            ->with('branch:id,name')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->where('is_active', true)
            ->get();

        // Get all stores for destination (filtered by permission later)
        $destinationStores = Store::select('id', 'name', 'code', 'branch_id')
            ->with('branch:id,name')
            ->where('is_active', true)
            ->get();

        $products = Product::select('id', 'name', 'sku', 'selling_price')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Transfers/Create', [
            'sourceStores' => $sourceStores,
            'destinationStores' => $destinationStores,
            'products' => $products,
            'selectedSource' => $request->from_store_id,
            'selectedDestination' => $request->to_store_id,
        ]);
    }

    /**
     * Store a newly created transfer.
     */
    public function store(TransferRequest $request)
    {
        try {
            // Check if user has access to source store
            if (!auth()->user()->canAccessStore($request->from_store_id)) {
                return redirect()->back()
                    ->with('error', 'Unauthorized to transfer from this store');
            }

            // Check if user has access to destination store
            if (!auth()->user()->canAccessStore($request->to_store_id)) {
                return redirect()->back()
                    ->with('error', 'Unauthorized to transfer to this store');
            }

            $transfer = $this->inventoryService->transferStock(
                $request->from_store_id,
                $request->to_store_id,
                $request->items,
                auth()->id(),
                $request->expected_delivery_date
            );

            return redirect()->route('transfers.show', $transfer->id)
                ->with('success', 'Transfer created successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Transfer failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified transfer.
     */
    public function show(Transfer $transfer)
    {
        // Check access to either source or destination store
        if (!auth()->user()->canAccessStore($transfer->from_store_id) && 
            !auth()->user()->canAccessStore($transfer->to_store_id)) {
            return redirect()->route('transfers.index')
                ->with('error', 'Unauthorized to view this transfer');
        }

        $transfer->load(['items.product', 'fromStore.branch', 'toStore.branch', 'creator', 'approver', 'receiver']);

        // Check if user can approve/receive
        $user = auth()->user();
        $canApprove = $user->hasPermissionTo('approve transfers') && 
                     $transfer->status === 'PENDING' &&
                     $user->canAccessStore($transfer->from_store_id);
        
        $canReceive = $user->hasPermissionTo('receive transfers') && 
                      $transfer->status === 'SHIPPED' &&
                      $user->canAccessStore($transfer->to_store_id);

        return Inertia::render('Transfers/Show', [
            'transfer' => $transfer,
            'canApprove' => $canApprove,
            'canReceive' => $canReceive,
        ]);
    }

    /**
     * Show form for editing transfer.
     */
    public function edit(Transfer $transfer)
    {
        // Only allow editing if transfer is pending
        if ($transfer->status !== 'PENDING') {
            return redirect()->route('transfers.show', $transfer->id)
                ->with('error', 'Only pending transfers can be edited');
        }

        // Check access
        if (!auth()->user()->canAccessStore($transfer->from_store_id)) {
            return redirect()->route('transfers.index')
                ->with('error', 'Unauthorized to edit this transfer');
        }

        $user = auth()->user();
        
        $sourceStores = Store::select('id', 'name', 'code', 'branch_id')
            ->with('branch:id,name')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->where('is_active', true)
            ->get();

        $destinationStores = Store::select('id', 'name', 'code', 'branch_id')
            ->with('branch:id,name')
            ->where('is_active', true)
            ->get();

        $products = Product::select('id', 'name', 'sku', 'selling_price')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Transfers/Edit', [
            'transfer' => $transfer->load('items'),
            'sourceStores' => $sourceStores,
            'destinationStores' => $destinationStores,
            'products' => $products,
        ]);
    }


    /**
     * Update the specified transfer.
     */
    public function update(TransferRequest $request, Transfer $transfer)
    {
        try {
            // Only allow update if transfer is pending
            if ($transfer->status !== 'PENDING') {
                return redirect()->back()
                    ->with('error', 'Only pending transfers can be updated');
            }

            // Check access
            if (!auth()->user()->canAccessStore($transfer->from_store_id)) {
                return redirect()->back()
                    ->with('error', 'Unauthorized to edit this transfer');
            }

            // Update transfer logic here
            $transfer->update([
                'from_store_id' => $request->from_store_id,
                'to_store_id' => $request->to_store_id,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
            ]);

            // Update items
            $transfer->items()->delete();
            foreach ($request->items as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity_requested' => $item['quantity'],
                    'status' => 'PENDING',
                ]);
            }

            return redirect()->route('transfers.show', $transfer->id)
                ->with('success', 'Transfer updated successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Update failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Approve the specified transfer.
     */
    public function approve(Request $request, Transfer $transfer)
    {
        try {
            if ($transfer->status !== 'PENDING') {
                return redirect()->back()
                    ->with('error', 'Only pending transfers can be approved');
            }

            // Check permission
            if (!auth()->user()->hasPermissionTo('approve transfers')) {
                return redirect()->back()
                    ->with('error', 'You do not have permission to approve transfers');
            }

            $transfer->update([
                'status' => 'PROCESSING',
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);

            return redirect()->route('transfers.show', $transfer->id)
                ->with('success', 'Transfer approved successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Approval failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark transfer as shipped
     */
    public function ship(Request $request, Transfer $transfer)
    {
        try {
            // Check if user has access to source store
            if (!auth()->user()->canAccessStore($transfer->from_store_id)) {
                return redirect()->back()
                    ->with('error', 'Unauthorized to ship from this store');
            }

            // Can only ship if status is PROCESSING
            if ($transfer->status !== 'PROCESSING') {
                return redirect()->back()
                    ->with('error', 'Only processing transfers can be shipped');
            }

            // Update transfer status
            $transfer->update([
                'status' => 'SHIPPED',
            ]);

            // Update items status
            foreach ($transfer->items as $item) {
                $item->update([
                    'quantity_shipped' => $item->quantity_requested,
                    'status' => 'SHIPPED',
                ]);
            }

            return redirect()->route('transfers.show', $transfer->id)
                ->with('success', 'Transfer marked as shipped');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Ship failed: ' . $e->getMessage());
        }
    }


    /**
     * Receive the specified transfer.
     */
    public function receive(Request $request, Transfer $transfer)
    {
        try {
            // Validate request
            $request->validate([
                'items' => 'required|array',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity_received' => 'required|integer|min:0',
            ]);

            // Check access to destination store
            if (!auth()->user()->canAccessStore($transfer->to_store_id)) {
                return redirect()->back()
                    ->with('error', 'Unauthorized to receive at this store');
            }

            if ($transfer->status !== 'SHIPPED') {
                return redirect()->back()
                    ->with('error', 'Only shipped transfers can be received');
            }

            $transfer = $this->inventoryService->receiveTransfer(
                $transfer->id,
                $request->items,
                auth()->id()
            );

            return redirect()->route('transfers.show', $transfer->id)
                ->with('success', 'Transfer received successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Receive failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Cancel the specified transfer.
     */
    public function cancel(Transfer $transfer)
    {
        try {
            // Only allow cancellation if not already received
            if (in_array($transfer->status, ['RECEIVED', 'CANCELLED'])) {
                return redirect()->back()
                    ->with('error', 'This transfer cannot be cancelled');
            }

            // Check access
            if (!auth()->user()->canAccessStore($transfer->from_store_id)) {
                return redirect()->back()
                    ->with('error', 'Unauthorized to cancel this transfer');
            }

            $transfer->update([
                'status' => 'CANCELLED',
            ]);

            return redirect()->route('transfers.index')
                ->with('success', 'Transfer cancelled successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Cancel failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete the specified transfer.
     */
    public function destroy(Transfer $transfer)
    {
        try {
            // Only admins can delete transfers
            if (!auth()->user()->hasRole('Administrator')) {
                return redirect()->back()
                    ->with('error', 'Only administrators can delete transfers');
            }

            // Only allow deletion if transfer is pending or cancelled
            if (!in_array($transfer->status, ['PENDING', 'CANCELLED'])) {
                return redirect()->back()
                    ->with('error', 'Only pending or cancelled transfers can be deleted');
            }

            $transfer->items()->delete();
            $transfer->delete();

            return redirect()->route('transfers.index')
                ->with('success', 'Transfer deleted successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }
}