<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_grouped_sidebar_renders_on_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Utama')
            ->assertSee('Master Data')
            ->assertSee('Admin');
    }

    public function test_breadcrumbs_render_on_create_and_edit_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'NAV-001',
            'price' => 5000,
            'stock' => 10,
        ]);
        $user = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($admin)->get('/products/create')->assertOk()->assertSee('Tambah Produk');
        $this->actingAs($admin)->get("/products/{$product->id}/edit")->assertOk()->assertSee('Ubah Produk');
        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('Tambah Pengguna');
        $this->actingAs($admin)->get("/users/{$user->id}/edit")->assertOk()->assertSee('Ubah Pengguna');
    }
}
