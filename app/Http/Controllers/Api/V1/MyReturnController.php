<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMyReturnRequest;
use App\Models\Rider;
use App\StockReturnStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MyReturnController extends Controller
{
    /**
     * A rider returns unsold pieces: N of them, or everything still held when no quantity is sent.
     * Each return stays pending until an admin verifies it.
     *
     * Pieces are taken from today's assignments oldest-first. Responds with the updated summary.
     *
     * @throws ValidationException
     */
    public function store(StoreMyReturnRequest $request): JsonResponse
    {
        $rider = $request->user()->rider;
        $requested = $request->filled('quantity_returned') ? $request->integer('quantity_returned') : null;

        DB::transaction(function () use ($rider, $requested): void {
            $assignments = $rider->assignmentsOn(today(), lock: true);
            $remaining = $assignments->sum(fn ($assignment): int => Rider::remainingIn($assignment));
            $quantity = $requested ?? $remaining;

            if ($remaining === 0 || $quantity > $remaining) {
                throw ValidationException::withMessages([
                    'quantity_returned' => [$remaining === 0
                        ? 'You have no stock left to return today.'
                        : "You only have {$remaining} pcs left to return."],
                ]);
            }

            $toReturn = $quantity;
            foreach ($assignments as $assignment) {
                $take = min(Rider::remainingIn($assignment), $toReturn);
                if ($take <= 0) {
                    continue;
                }

                $assignment->stockReturns()->create([
                    'quantity_returned' => $take,
                    'return_date' => now(),
                    'status' => StockReturnStatus::Pending,
                ]);

                $toReturn -= $take;
                if ($toReturn === 0) {
                    break;
                }
            }
        });

        return response()->json(['data' => $rider->todaySummary()], JsonResponse::HTTP_CREATED);
    }
}
