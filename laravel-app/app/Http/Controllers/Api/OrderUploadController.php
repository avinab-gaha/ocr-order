<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderMasterRequest;
use App\Http\Requests\UploadOrderRequest;
use App\Http\Resources\OrderMasterResource;
use App\Models\OrderMaster;
use App\Services\OrderIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderUploadController extends Controller
{
    public function __construct(
        protected OrderIngestionService $ingestionService,
    ) {
    }

    /**
     * POST /api/orders/upload
     * Uploads a document, runs OCR + LLM mapping, auto-creates
     * order_masters/order_details, and returns a JSON preview.
     */
    public function upload(UploadOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->ingestionService->ingest(
                $request->file('file'),
                $request->input('llm_provider'),
            );

            $status = $order->status === 'failed' ? 422 : 201;

            return (new OrderMasterResource($order))
                ->response()
                ->setStatusCode($status);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/orders
     */
    public function index(Request $request): JsonResponse
    {
        $orders = OrderMaster::with('details')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return OrderMasterResource::collection($orders)->response();
    }

    /**
     * GET /api/orders/{order}
     */
    public function show(OrderMaster $order): JsonResponse
    {
        return (new OrderMasterResource($order->load('details')))
            ->response();
    }

    /**
     * PATCH /api/orders/{order}
     * Corrects header fields the OCR/LLM pipeline misread — vendor name,
     * order number, date, total, etc. — before the order is confirmed.
     * Only fields present in the request body are changed.
     */
    public function update(UpdateOrderMasterRequest $request, OrderMaster $order): JsonResponse
    {
        $order->assertEditable();

        $order->update($request->validated());

        return (new OrderMasterResource($order->load('details')))
            ->response();
    }

    /**
     * POST /api/orders/{order}/confirm
     * Marks a previewed order as confirmed once a human has reviewed it.
     */
    public function confirm(OrderMaster $order): JsonResponse
    {
        $order->update(['status' => 'confirmed']);

        return (new OrderMasterResource($order->load('details')))
            ->response();
    }

    /**
     * POST /api/orders/{order}/reopen
     * Moves a confirmed order back to pending so it can be edited again.
     */
    public function reopen(OrderMaster $order): JsonResponse
    {
        $order->update(['status' => 'pending']);

        return (new OrderMasterResource($order->load('details')))
            ->response();
    }

    /**
     * POST /api/orders/{order}/recalculate-total
     * Re-sums total_amount from current line items. Useful after manual
     * item corrections when you want the total to follow the items
     * rather than keep the (possibly misread) figure the LLM extracted.
     */
    public function recalculateTotal(OrderMaster $order): JsonResponse
    {
        $order->assertEditable();

        $order->recalculateTotal();

        return (new OrderMasterResource($order->load('details')))
            ->response();
    }
}
