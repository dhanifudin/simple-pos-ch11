<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $category = Category::create(['name' => 'Minuman']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'PAY-001',
            'price' => 5000,
            'stock' => 10,
        ]);
    }

    public function test_cash_payment_records_amount_paid_and_change(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();

        $response = $this->actingAs($kasir)->post('/pos', [
            'items' => [['product_id' => $product->id, 'qty' => 3]], // total 15000
            'amount_paid' => 20000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'total' => 15000,
            'payment_method' => 'tunai',
            'amount_paid' => 20000,
            'change_due' => 5000,
        ]);
    }

    public function test_insufficient_cash_is_rejected(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();

        $response = $this->actingAs($kasir)->post('/pos', [
            'items' => [['product_id' => $product->id, 'qty' => 3]], // total 15000
            'amount_paid' => 10000,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('transactions', 0);
        $this->assertEquals(10, $product->fresh()->stock); // stock untouched, transaction rolled back
    }

    public function test_omitting_payment_fields_defaults_to_exact_cash(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();

        $response = $this->actingAs($kasir)->post('/pos', [
            'items' => [['product_id' => $product->id, 'qty' => 3]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'total' => 15000,
            'payment_method' => 'tunai',
            'amount_paid' => 15000,
            'change_due' => 0,
        ]);
    }
}
