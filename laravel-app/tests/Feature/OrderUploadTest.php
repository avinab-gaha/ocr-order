<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_order_document_returns_validation_error(): void
    {
        Http::fake([
            'localhost:8001/ocr' => Http::response([
                'text' => 'A beautiful sunset over the mountains with trees in the foreground',
                'lines' => [],
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('sunset.jpg');

        $response = $this->postJson('/api/orders/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'The uploaded image doesn\'t appear to be a supported order image/document',
        ]);
    }

    public function test_upload_creates_order_master_and_details_preview(): void
    {
        config(['services.llm.default' => 'openai']);
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'localhost:8001/ocr' => Http::response([
                'text' => "Invoice #INV-2001\nAcme Supplies\n2 x Widget @ 10.00 = 20.00\nTotal: 20.00",
                'lines' => [],
            ], 200),

            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'master' => [
                                    'order_code' => 'INV-2001',
                                    'customer_name' => 'Acme Supplies',
                                    'service_classification' => 'BS',
                                    'planned_service_date' => '2026-07-09',
                                    'total_amount' => 20.00,
                                ],
                                'items' => [
                                    [
                                        'service_name1' => 'Widget',
                                        'quantity' => 2,
                                        'unit' => 'pcs',
                                        'unit_price' => 10.00,
                                        'amount' => 20.00,
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('invoice.jpg');

        $response = $this->postJson('/api/orders/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.order_code', 'INV-2001')
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.service_name1', 'Widget');

        $this->assertDatabaseHas('order_masters', ['order_code' => 'INV-2001']);
        $this->assertDatabaseHas('order_details', ['service_name1' => 'Widget', 'amount' => 20.00]);
    }
}
