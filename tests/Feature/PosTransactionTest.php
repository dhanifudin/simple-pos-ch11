<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_storing_a_transaction_decrements_product_stock_and_computes_total(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'TST-001',
            'price' => 5000,
            'stock' => 10,
        ]);

        $response = $this->actingAs($kasir)->post('/pos', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 3],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', ['user_id' => $kasir->id, 'total' => 15000]);
        $this->assertEquals(7, $product->fresh()->stock);
    }

    public function test_transaction_requires_at_least_one_item(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $response = $this->actingAs($kasir)->post('/pos', ['items' => []]);

        $response->assertSessionHasErrors('items');
    }
}
