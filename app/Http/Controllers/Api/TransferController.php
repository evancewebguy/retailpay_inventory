<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\InventoryService;
use App\Http\Requests\TransferRequest;
use App\Models\Transfer;
use Illuminate\Routing\Controllers\Middleware;


class TransferController extends Controller
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
            new Middleware('permission:create transfers', except: ['index', 'show']),
            new Middleware('permission:approve transfers', only: ['approve']),
        ];
    }

    public function index(Request $request)
    {
        $transfers = Transfer::with(['fromStore', 'toStore', 'items.product', 'creator'])
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->from_store_id, function ($query, $storeId) {
                return $query->where('from_store_id', $storeId);
            })
            ->when($request->to_store_id, function ($query, $storeId) {
                return $query->where('to_store_id', $storeId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transfers);
    }

    public function store(TransferRequest $request)
    {
        try {
            // Check if user has access to source store
            if (!auth()->user()->canAccessStore($request->from_store_id)) {
                return response()->json([
                    'message' => 'Unauthorized to transfer from this store'
                ], 403);
            }

            // Check if user has access to destination store
            if (!auth()->user()->canAccessStore($request->to_store_id)) {
                return response()->json([
                    'message' => 'Unauthorized to transfer to this store'
                ], 403);
            }

            $transfer = $this->inventoryService->transferStock(
                $request->from_store_id,
                $request->to_store_id,
                $request->items,
                auth()->id(),
                $request->expected_delivery_date
            );
            

            return response()->json([
                'message' => 'Transfer created successfully',
                'data' => $transfer->load(['items.product', 'fromStore', 'toStore'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Transfer failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(Transfer $transfer)
    {
        // Check access to either source or destination store
        if (!auth()->user()->canAccessStore($transfer->from_store_id) && 
            !auth()->user()->canAccessStore($transfer->to_store_id)) {
            return response()->json([
                'message' => 'Unauthorized to view this transfer'
            ], 403);
        }

        return response()->json(
            $transfer->load(['items.product', 'fromStore', 'toStore', 'creator', 'approver'])
        );
    }

    public function approve(Request $request, Transfer $transfer)
    {
        try {
            if ($transfer->status !== 'PENDING') {
                return response()->json([
                    'message' => 'Only pending transfers can be approved'
                ], 400);
            }

            $transfer->update([
                'status' => 'PROCESSING',
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);

            return response()->json([
                'message' => 'Transfer approved successfully',
                'data' => $transfer
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Approval failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function receive(Request $request, Transfer $transfer)
    {
        try {
            if (!auth()->user()->canAccessStore($transfer->to_store_id)) {
                return response()->json([
                    'message' => 'Unauthorized to receive at this store'
                ], 403);
            }

            $transfer = $this->inventoryService->receiveTransfer(
                $transfer->id,
                $request->items,
                auth()->id()
            );

            return response()->json([
                'message' => 'Transfer received successfully',
                'data' => $transfer->load(['items.product'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Receive failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}