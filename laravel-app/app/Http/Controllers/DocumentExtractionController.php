<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmExtractionRequest;
use App\Http\Requests\ExtractDocumentRequest;
use App\Http\Resources\OrderMasterResource;
use App\Services\DocumentExtractionService;
use App\Services\OrderConfirmationService;
use RuntimeException;

class DocumentExtractionController extends Controller
{
    public function __construct(
        protected DocumentExtractionService $extractionService,
        protected OrderConfirmationService $confirmationService,
    ) {
    }

    public function index()
    {
        return view('extraction');
    }

    public function extract(ExtractDocumentRequest $request)
    {
        try {
            $result = $this->extractionService->extract(
                $request->file('file'),
                $request->input('llm_provider'),
            );

            return response()->json($result);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'denied') && str_contains($message, 'Gemini')) {
                $message .= ' — Enable the Generative Language API in Google Cloud Console and ensure billing is active.';
            }

            return response()->json([
                'error' => $message,
            ], 422);
        }
    }

    public function confirm(ConfirmExtractionRequest $request)
    {
        $order = $this->confirmationService->confirm(
            $request->only(['master', 'items']),
            $request->input('field_confidence', []),
        );

        return (new OrderMasterResource($order))
            ->response()
            ->setStatusCode(201);
    }
}
