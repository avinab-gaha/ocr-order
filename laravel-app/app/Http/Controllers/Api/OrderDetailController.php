<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderDetailRequest;
use App\Http\Requests\UpdateOrderDetailRequest;
use App\Http\Resources\OrderDetailResource;
use App\Models\OrderDetail;
use App\Models\OrderMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class OrderDetailController extends Controller
{
    /**
     * POST /api/orders/{order}/items
     * Adds a line item the OCR/LLM step missed entirely.
     */
    public function store(StoreOrderDetailRequest $request, OrderMaster $order): JsonResponse
    {
        $order->assertEditable();

        $data = $request->validated();
        $data['line_no'] = $data['line_no'] ?? ((int) $order->details()->max('line_no') + 1);

        $item = $order->details()->create($data);

        return (new OrderDetailResource($item))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * PATCH /api/orders/{order}/items/{item}
     * Corrects a misread field (wrong quantity, garbled item name, etc).
     * Only fields present in the request body are changed.
     */
    public function update(UpdateOrderDetailRequest $request, OrderMaster $order, OrderDetail $item): JsonResponse
    {
        $order->assertEditable();
        abort_unless($item->order_master_id === $order->id, Response::HTTP_NOT_FOUND);

        $data = $request->validated();
        $item->fill($data);

        $item->save();

        return (new OrderDetailResource($item))->response();
    }

    /**
     * DELETE /api/orders/{order}/items/{item}
     * Removes a line item the OCR/LLM step hallucinated or duplicated.
     */
    public function destroy(OrderMaster $order, OrderDetail $item): JsonResponse
    {
        $order->assertEditable();
        abort_unless($item->order_master_id === $order->id, Response::HTTP_NOT_FOUND);

        $item->delete();

        return response()->json(['deleted' => true]);
    }
}
