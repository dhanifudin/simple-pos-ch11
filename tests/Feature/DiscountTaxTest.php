<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountTaxTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $category = Category::create(['name' => 'Minuman']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'DTX-001',
            'price' => 10000,
            'stock' => 10,
        ]);
    }

    public function test_discount_reduces_total(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();

        $response = $this->actingAs($kasir)->post('/pos', [
            'items' => [['product_id' => $product->id, 'qty' => 3]], // subtotal 30000
            'discount' => 5000,
            'amount_paid' => 25000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'total' => 25000,
            'discount' => 5000,
            'tax' => 0,
            'amount_paid' => 25000,
            'change_due' => 0,
        ]);
    }

    public function test_discount_is_capped_at_subtotal(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();

        $response = $this->actingAs($kasir)->post('/pos', [
            'items' => [['product_id' => $product->id, 'qty' => 1]], // subtotal 10000
            'discount' => 50000,
            'amount_paid' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'total' => 0,
            'discount' => 10000,
            'tax' => 0,
        ]);
    }

    public function test_tax_computed_from_shop_setting(): void
    {
        ShopSetting::current()->update(['tax_percent' => 10]);

        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();

        $response = $this->actingAs($kasir)->post('/pos', [
            'items' => [['product_id' => $product->id, 'qty' => 2]], // subtotal 20000
            'amount_paid' => 22000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'total' => 22000,
            'discount' => 0,
            'tax' => 2000,
            'amount_paid' => 22000,
            'change_due' => 0,
        ]);
    }

    public function test_tax_applies_after_discount(): void
    {
        ShopSetting::current()->update(['tax_percent' => 10]);

        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();

        $response = $this->actingAs($kasir)->post('/pos', [
            'items' => [['product_id' => $product->id, 'qty' => 2]], // subtotal 20000
            'discount' => 5000,
            'amount_paid' => 16500,
        ]);

        $response->assertRedirect();
        // (20000 - 5000) * 10% = 1500 tax, grand total = 15000 + 1500 = 16500
        $this->assertDatabaseHas('transactions', [
            'total' => 16500,
            'discount' => 5000,
            'tax' => 1500,
        ]);

        $response->assertRedirect();
        $this->actingAs($kasir)->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Diskon')
            ->assertSee('Pajak')
            ->assertSee('Subtotal');
    }

    public function test_default_tax_percent_is_zero_and_matches_prior_behavior(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();

        $response = $this->actingAs($kasir)->post('/pos', [
            'items' => [['product_id' => $product->id, 'qty' => 3]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'total' => 30000,
            'discount' => 0,
            'tax' => 0,
            'amount_paid' => 30000,
            'change_due' => 0,
        ]);
    }
}
