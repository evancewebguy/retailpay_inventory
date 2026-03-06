<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStoreAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // If route has store parameter
        $storeId = $request->route('store') ?? $request->store_id ?? $request->from_store_id ?? $request->to_store_id;
        
        if ($storeId && !$user->canAccessStore($storeId)) {
            return response()->json([
                'message' => 'You do not have access to this store'
            ], 403);
        }

        return $next($request);
    }

}
