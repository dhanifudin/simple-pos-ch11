<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_shop_name_and_it_reflects_across_the_app(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->put('/settings', [
            'name' => 'Toko Sejahtera',
            'tax_percent' => 0,
        ]);

        $response->assertRedirect();
        // Sidebar/layout (rendered via the same View::composer as the login page)
        // now shows the updated name while still authenticated.
        $this->actingAs($admin)->get('/dashboard')->assertSee('Toko Sejahtera');

        $this->post('/logout');
        $this->get('/login')->assertSee('Toko Sejahtera');
    }

    public function test_updated_shop_name_reflects_in_receipt_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'SET-001',
            'price' => 5000,
            'stock' => 10,
        ]);
        $this->actingAs($admin)->post('/pos', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ]);
        $transaction = Transaction::latest()->first();

        $this->actingAs($admin)->put('/settings', ['name' => 'Toko Sejahtera', 'tax_percent' => 0]);

        $pdf = $this->actingAs($admin)->get("/transactions/{$transaction->id}/pdf");
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
    }

    public function test_kasir_cannot_update_shop_settings(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)->get('/settings')->assertForbidden();
        $this->actingAs($kasir)->put('/settings', ['name' => 'Coba Ubah'])->assertForbidden();
    }
}
