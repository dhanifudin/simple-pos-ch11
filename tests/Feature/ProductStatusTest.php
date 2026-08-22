<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_product_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'TGL-001',
            'price' => 5000,
            'stock' => 10,
        ]);

        $this->assertTrue($product->fresh()->is_active);

        $this->actingAs($admin)->patch("/products/{$product->id}/status")->assertRedirect();
        $this->assertFalse($product->fresh()->is_active);

        $this->actingAs($admin)->patch("/products/{$product->id}/status")->assertRedirect();
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_inactive_product_does_not_appear_on_pos_page(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Nonaktif',
            'sku' => 'INA-001',
            'price' => 5000,
            'stock' => 10,
            'is_active' => false,
        ]);

        $response = $this->actingAs($kasir)->get('/pos');

        $response->assertOk();
        $response->assertDontSee('Produk Nonaktif');
    }

    public function test_transaction_rejects_inactive_product(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Nonaktif',
            'sku' => 'INA-002',
            'price' => 5000,
            'stock' => 10,
            'is_active' => false,
        ]);

        $response = $this->actingAs($kasir)->post('/pos', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.product_id');
    }
}
