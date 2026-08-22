<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/categories', ['name' => 'Minuman']);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'Minuman']);
    }

    public function test_admin_can_rename_a_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);

        $response = $this->actingAs($admin)->put("/categories/{$category->id}", ['name' => 'Minuman Dingin']);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Minuman Dingin']);
    }

    public function test_deleting_a_category_with_products_is_blocked_but_succeeds_once_emptied(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'CAT-001',
            'price' => 5000,
            'stock' => 10,
        ]);

        $blocked = $this->actingAs($admin)->delete("/categories/{$category->id}");
        $blocked->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        $product->delete();

        $allowed = $this->actingAs($admin)->delete("/categories/{$category->id}");
        $allowed->assertSessionHas('status');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_admin_can_view_the_category_index_with_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'CAT-VIEW-001',
            'price' => 5000,
            'stock' => 10,
        ]);

        $response = $this->actingAs($admin)->get('/categories');

        $response->assertOk()
            ->assertSee('Minuman')
            ->assertSee('1 produk')
            ->assertSee('Ubah')
            ->assertSee('Hapus');
    }

    public function test_kasir_cannot_manage_categories(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['name' => 'Minuman']);

        $this->actingAs($kasir)->get('/categories')->assertForbidden();
        $this->actingAs($kasir)->post('/categories', ['name' => 'Coba'])->assertForbidden();
        $this->actingAs($kasir)->put("/categories/{$category->id}", ['name' => 'Coba'])->assertForbidden();
        $this->actingAs($kasir)->delete("/categories/{$category->id}")->assertForbidden();
    }

    public function test_product_index_filters_by_category_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $minuman = Category::create(['name' => 'Minuman']);
        $makanan = Category::create(['name' => 'Makanan']);
        Product::create([
            'category_id' => $minuman->id, 'name' => 'Air Mineral', 'sku' => 'CAT-002', 'price' => 5000, 'stock' => 10,
        ]);
        Product::create([
            'category_id' => $makanan->id, 'name' => 'Nasi Goreng', 'sku' => 'CAT-003', 'price' => 15000, 'stock' => 10,
        ]);

        $response = $this->actingAs($admin)->get("/products?category_id={$minuman->id}");

        $response->assertOk()
            ->assertSee('Air Mineral')
            ->assertDontSee('Nasi Goreng')
            ->assertSee('Kategori: Minuman');
    }
}
