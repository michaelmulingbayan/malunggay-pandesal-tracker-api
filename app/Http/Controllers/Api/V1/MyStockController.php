<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyStockController extends Controller
{
    /**
     * The signed-in rider's stock and sales for today.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $rider = $request->user()->rider;
        abort_if($rider === null, JsonResponse::HTTP_FORBIDDEN, 'Only riders have their own stock.');

        return response()->json(['data' => $rider->todaySummary()]);
    }
}
