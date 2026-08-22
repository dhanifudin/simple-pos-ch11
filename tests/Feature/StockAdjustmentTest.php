<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $stock = 20): Product
    {
        $category = Category::create(['name' => 'Minuman']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'ADJ-001',
            'price' => 5000,
            'stock' => $stock,
        ]);
    }

    public function test_admin_can_increase_stock_with_a_logged_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct(20);

        $response = $this->actingAs($admin)->patch("/products/{$product->id}/stock", [
            'delta' => 10,
            'reason' => 'Restock dari supplier',
        ]);

        $response->assertRedirect();
        $this->assertEquals(30, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'delta' => 10,
            'stock_after' => 30,
            'reason' => 'Restock dari supplier',
        ]);
    }

    public function test_admin_can_decrease_stock_within_bounds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct(20);

        $this->actingAs($admin)->patch("/products/{$product->id}/stock", [
            'delta' => -5,
            'reason' => 'Koreksi stock opname',
        ]);

        $this->assertEquals(15, $product->fresh()->stock);
    }

    public function test_adjustment_that_would_go_negative_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct(5);

        $response = $this->actingAs($admin)->patch("/products/{$product->id}/stock", [
            'delta' => -10,
            'reason' => 'Koreksi berlebihan',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_adjustments', 0);
    }

    public function test_zero_delta_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct(20);

        $response = $this->actingAs($admin)->patch("/products/{$product->id}/stock", [
            'delta' => 0,
            'reason' => 'Tidak ada perubahan',
        ]);

        $response->assertSessionHasErrors('delta');
        $this->assertEquals(20, $product->fresh()->stock);
    }

    public function test_kasir_cannot_adjust_stock(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct(20);

        $this->actingAs($kasir)->patch("/products/{$product->id}/stock", [
            'delta' => 10,
            'reason' => 'Coba-coba',
        ])->assertForbidden();
    }

    public function test_editing_a_product_never_changes_its_stock_even_if_submitted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct(20);
        $category = $product->category;

        $this->actingAs($admin)->put("/products/{$product->id}", [
            'category_id' => $category->id,
            'name' => 'Air Mineral Updated',
            'sku' => $product->sku,
            'price' => 6000,
            'stock' => 999, // crafted/forced — must be ignored server-side
            'is_active' => '1',
        ]);

        $fresh = $product->fresh();
        $this->assertEquals('Air Mineral Updated', $fresh->name);
        $this->assertEquals(20, $fresh->stock);
    }
}
