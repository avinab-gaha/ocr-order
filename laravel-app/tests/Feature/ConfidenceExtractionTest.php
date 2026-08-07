<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfidenceExtractionTest extends TestCase
{
    use RefreshDatabase;

    private function fakeLlmResponse(array $master, array $items, bool $wrapped = true): void
    {
        config(['services.llm.default' => 'openai']);
        config(['services.openai.api_key' => 'test-key']);

        if ($wrapped) {
            $content = json_encode([
                'master' => $master,
                'items' => $items,
            ]);
        } else {
            $content = json_encode([
                'master' => array_map(fn ($f) => $f['value'] ?? $f, $master),
                'items' => array_map(function ($item) {
                    $result = [];
                    foreach ($item as $k => $v) {
                        $result[$k] = $v['value'] ?? $v;
                    }
                    return $result;
                }, $items),
            ]);
        }

        Http::fake([
            'localhost:8001/ocr' => Http::response([
                'text' => "INV-2001\nAcme Supplies\nTotal: 100.00",
                'lines' => [],
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ], 200),
        ]);
    }

    public function test_unwrap_confidence_flattens_wrapped_values(): void
    {
        $master = [
            'order_code' => ['value' => 'INV-2001', 'confidence' => 'high'],
            'customer_name' => ['value' => 'Acme Supplies', 'confidence' => 'high'],
            'service_classification' => ['value' => 'BS', 'confidence' => 'high'],
            'planned_service_date' => ['value' => '2026-07-09', 'confidence' => 'high'],
            'total_amount' => ['value' => 100.00, 'confidence' => 'high'],
        ];
        $items = [
            ['service_name1' => ['value' => 'Widget', 'confidence' => 'high'], 'quantity' => ['value' => 2, 'confidence' => 'high'], 'unit' => ['value' => null, 'confidence' => 'low'], 'unit_price' => ['value' => 50.00, 'confidence' => 'high'], 'amount' => ['value' => 100.00, 'confidence' => 'high']],
        ];

        $this->fakeLlmResponse($master, $items, true);

        $file = UploadedFile::fake()->image('invoice.jpg');
        $response = $this->postJson('/api/orders/upload', ['file' => $file]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('order_masters', [
            'order_code' => 'INV-2001',
            'customer_name' => 'Acme Supplies',
            'service_classification' => 'BS',
            'status' => 'flagged',
        ]);

        $master = $response->json('data');
        $this->assertEquals('INV-2001', $master['order_code']);
        $this->assertEquals('Acme Supplies', $master['customer_name']);
    }

    public function test_unwrap_confidence_sets_pending_when_no_low_fields(): void
    {
        $master = [
            'order_code' => ['value' => 'INV-2001', 'confidence' => 'high'],
            'customer_name' => ['value' => 'Acme Supplies', 'confidence' => 'high'],
            'service_classification' => ['value' => 'BS', 'confidence' => 'high'],
            'planned_service_date' => ['value' => '2026-07-09', 'confidence' => 'medium'],
            'total_amount' => ['value' => 100.00, 'confidence' => 'high'],
        ];
        $items = [
            ['service_name1' => ['value' => 'Widget', 'confidence' => 'high'], 'quantity' => ['value' => 2, 'confidence' => 'high'], 'unit' => ['value' => null, 'confidence' => 'high'], 'unit_price' => ['value' => 50.00, 'confidence' => 'high'], 'amount' => ['value' => 100.00, 'confidence' => 'high']],
        ];

        $this->fakeLlmResponse($master, $items, true);

        $file = UploadedFile::fake()->image('invoice.jpg');
        $response = $this->postJson('/api/orders/upload', ['file' => $file]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_ingestion_sets_flagged_when_any_field_is_low_confidence(): void
    {
        $master = [
            'order_code' => ['value' => 'INV-2001', 'confidence' => 'high'],
            'customer_name' => ['value' => 'Acme Supplies', 'confidence' => 'low'],
            'service_classification' => ['value' => 'BS', 'confidence' => 'high'],
            'planned_service_date' => ['value' => '2026-07-09', 'confidence' => 'high'],
            'total_amount' => ['value' => 100.00, 'confidence' => 'high'],
        ];
        $items = [
            ['service_name1' => ['value' => 'Widget', 'confidence' => 'high'], 'quantity' => ['value' => 2, 'confidence' => 'high'], 'unit' => ['value' => null, 'confidence' => 'low'], 'unit_price' => ['value' => 50.00, 'confidence' => 'high'], 'amount' => ['value' => 100.00, 'confidence' => 'high']],
        ];

        $this->fakeLlmResponse($master, $items, true);

        $file = UploadedFile::fake()->image('invoice.jpg');
        $response = $this->postJson('/api/orders/upload', ['file' => $file]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'flagged');
    }

    public function test_backward_compatible_flat_response(): void
    {
        $master = [
            'order_code' => 'INV-2001',
            'customer_name' => 'Acme Supplies',
            'service_classification' => 'BS',
            'planned_service_date' => '2026-07-09',
            'total_amount' => 100.00,
        ];
        $items = [
            ['service_name1' => 'Widget', 'quantity' => 2, 'unit' => null, 'unit_price' => 50.00, 'amount' => 100.00],
        ];

        $this->fakeLlmResponse($master, $items, false);

        $file = UploadedFile::fake()->image('invoice.jpg');
        $response = $this->postJson('/api/orders/upload', ['file' => $file]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_field_confidence_is_persisted(): void
    {
        $master = [
            'order_code' => ['value' => 'PO-100', 'confidence' => 'high'],
            'customer_name' => ['value' => 'Test Vendor', 'confidence' => 'medium'],
            'service_classification' => ['value' => 'BS', 'confidence' => 'high'],
            'planned_service_date' => ['value' => '2026-07-15', 'confidence' => 'high'],
            'total_amount' => ['value' => 500.00, 'confidence' => 'high'],
        ];
        $items = [
            ['service_name1' => ['value' => 'Item A', 'confidence' => 'high'], 'quantity' => ['value' => 5, 'confidence' => 'high'], 'unit' => ['value' => null, 'confidence' => 'low'], 'unit_price' => ['value' => 100.00, 'confidence' => 'high'], 'amount' => ['value' => 500.00, 'confidence' => 'high']],
        ];

        $this->fakeLlmResponse($master, $items, true);

        $file = UploadedFile::fake()->image('invoice.jpg');
        $response = $this->postJson('/api/orders/upload', ['file' => $file]);

        $response->assertStatus(201);

        $order = \App\Models\OrderMaster::first();
        $this->assertNotNull($order->field_confidence);
        $this->assertIsArray($order->field_confidence);
        $this->assertArrayHasKey('master.customer_name', $order->field_confidence);
        $this->assertEquals('medium', $order->field_confidence['master.customer_name']);
    }
}
