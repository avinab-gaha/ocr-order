<?php

namespace Tests\Feature;

use App\Models\OrderMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function makePendingOrder(): OrderMaster
    {
        $order = OrderMaster::create([
            'status' => 'pending',
            'order_code' => 'INV-0RIG',
            'customer_name' => 'Acrne Supplies',
            'service_classification' => 'XX',
            'total_amount' => 20.00,
        ]);

        $order->details()->create([
            'line_no' => 1,
            'service_name1' => 'Widget',
            'quantity' => 2,
            'unit_price' => 10.00,
            'amount' => 20.00,
        ]);

        return $order;
    }

    public function test_master_fields_can_be_corrected(): void
    {
        $order = $this->makePendingOrder();

        $response = $this->patchJson("/api/orders/{$order->id}", [
            'order_code' => 'INV-2001',
            'customer_name' => 'Acme Supplies',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.order_code', 'INV-2001')
            ->assertJsonPath('data.customer_name', 'Acme Supplies');

        $this->assertDatabaseHas('order_masters', [
            'id' => $order->id,
            'order_code' => 'INV-2001',
            'customer_name' => 'Acme Supplies',
        ]);
    }

    public function test_confirmed_orders_cannot_be_edited_until_reopened(): void
    {
        $order = $this->makePendingOrder();
        $order->update(['status' => 'confirmed']);

        $this->patchJson("/api/orders/{$order->id}", ['customer_name' => 'New Name'])
            ->assertStatus(409);

        $this->postJson("/api/orders/{$order->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->patchJson("/api/orders/{$order->id}", ['customer_name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.customer_name', 'New Name');
    }

    public function test_a_missed_line_item_can_be_added(): void
    {
        $order = $this->makePendingOrder();

        $response = $this->postJson("/api/orders/{$order->id}/items", [
            'service_name1' => 'Gadget',
            'quantity' => 1,
            'unit_price' => 5.00,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.service_name1', 'Gadget');

        $this->assertDatabaseHas('order_details', [
            'order_master_id' => $order->id,
            'service_name1' => 'Gadget',
        ]);
    }

    public function test_a_misread_line_item_can_be_corrected(): void
    {
        $order = $this->makePendingOrder();
        $item = $order->details()->first();

        $response = $this->patchJson("/api/orders/{$order->id}/items/{$item->id}", [
            'quantity' => 3,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.quantity', '3.000');
    }

    public function test_a_hallucinated_line_item_can_be_deleted(): void
    {
        $order = $this->makePendingOrder();
        $item = $order->details()->first();

        $this->deleteJson("/api/orders/{$order->id}/items/{$item->id}")
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('order_details', ['id' => $item->id]);
    }

    public function test_total_can_be_recalculated_from_current_items(): void
    {
        $order = $this->makePendingOrder();
        $order->details()->create([
            'line_no' => 2,
            'service_name1' => 'Gadget',
            'quantity' => 1,
            'unit_price' => 5.00,
            'amount' => 5.00,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/recalculate-total");

        $response->assertOk()
            ->assertJsonPath('data.total_amount', '25.00');
    }
}
