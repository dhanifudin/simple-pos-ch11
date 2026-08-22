<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_can_look_up_a_known_sku(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'SCAN-001',
            'price' => 5000,
            'stock' => 10,
        ]);

        $response = $this->actingAs($kasir)->getJson('/pos/lookup?sku=SCAN-001');

        $response->assertOk()->assertJson([
            'id' => $product->id,
            'name' => 'Air Mineral',
            'price' => 5000,
            'stock' => 10,
        ]);
    }

    public function test_unknown_sku_returns_404(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)->getJson('/pos/lookup?sku=NOPE-999')->assertNotFound();
    }

    public function test_inactive_or_out_of_stock_sku_returns_404(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['name' => 'Minuman']);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Nonaktif',
            'sku' => 'SCAN-002',
            'price' => 5000,
            'stock' => 10,
            'is_active' => false,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Stok Habis',
            'sku' => 'SCAN-003',
            'price' => 5000,
            'stock' => 0,
        ]);

        $this->actingAs($kasir)->getJson('/pos/lookup?sku=SCAN-002')->assertNotFound();
        $this->actingAs($kasir)->getJson('/pos/lookup?sku=SCAN-003')->assertNotFound();
    }
}
