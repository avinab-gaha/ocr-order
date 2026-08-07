<?php

namespace Tests\Feature;

use App\Models\OrderMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractionConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_creates_order_master_and_details(): void
    {
        $payload = [
            'master' => [
                'order_code' => 'INV-2001',
                'customer_name' => 'Acme Supplies',
                'service_classification' => 'BS',
                'planned_service_date' => '2026-07-09',
                'total_amount' => 100.00,
            ],
            'items' => [
                ['service_name1' => 'Widget', 'quantity' => 2, 'unit' => 'pcs', 'unit_price' => 50.00, 'amount' => 100.00],
            ],
        ];

        $response = $this->postJson('/extract/confirm', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.total_amount', '100.00')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.service_name1', 'Widget');

        $this->assertDatabaseHas('order_masters', [
            'order_code' => 'INV-2001',
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('order_details', [
            'service_name1' => 'Widget',
            'amount' => 100.00,
        ]);
    }

    public function test_confirm_with_edited_values(): void
    {
        $payload = [
            'master' => [
                'order_code' => 'INV-2001',
                'customer_name' => 'Corrected Customer Name',
                'service_classification' => 'BS',
                'planned_service_date' => '2026-07-10',
                'total_amount' => 250.00,
            ],
            'items' => [
                ['service_name1' => 'Edited Item', 'quantity' => 5, 'unit' => 'box', 'unit_price' => 50.00, 'amount' => 250.00],
            ],
        ];

        $response = $this->postJson('/extract/confirm', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_name', 'Corrected Customer Name')
            ->assertJsonPath('data.service_classification', 'BS')
            ->assertJsonPath('data.items.0.service_name1', 'Edited Item');

        $this->assertDatabaseHas('order_details', [
            'service_name1' => 'Edited Item',
            'quantity' => 5,
        ]);
    }

    public function test_confirm_preserves_field_confidence(): void
    {
        $fieldConfidence = [
            'master.order_code' => 'high',
            'master.customer_name' => 'high',
            'master.service_classification' => 'low',
            'master.total_amount' => 'high',
        ];

        $payload = [
            'master' => [
                'order_code' => 'INV-2001',
                'customer_name' => 'Acme Supplies',
                'planned_service_date' => '2026-07-09',
                'total_amount' => 100.00,
            ],
            'items' => [
                ['service_name1' => 'Widget', 'quantity' => 2, 'unit' => null, 'unit_price' => 50.00, 'amount' => 100.00],
            ],
            'field_confidence' => $fieldConfidence,
        ];

        $response = $this->postJson('/extract/confirm', $payload);

        $response->assertStatus(201);

        $order = OrderMaster::first();
        $this->assertNotNull($order->field_confidence);
        $this->assertEquals($fieldConfidence, $order->field_confidence);
    }

    public function test_confirm_accepts_item_with_service_name(): void
    {
        $payload = [
            'master' => [
                'order_code' => 'SVC-001',
                'customer_name' => 'Test Corp',
            ],
            'items' => [
                ['service_name1' => 'ベビーシッター基本', 'quantity' => 1, 'unit_price' => 3000],
            ],
        ];

        $response = $this->postJson('/extract/confirm', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_confirm_rejects_empty_payload(): void
    {
        $response = $this->postJson('/extract/confirm', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['master']);
    }

    public function test_confirm_without_items_creates_master_only(): void
    {
        $payload = [
            'master' => [
                'order_code' => 'INV-2001',
                'customer_name' => 'Acme Supplies',
                'total_amount' => 0,
            ],
        ];

        $response = $this->postJson('/extract/confirm', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('order_masters', ['order_code' => 'INV-2001']);
        $this->assertEquals(0, OrderMaster::first()->details()->count());
    }
}
