<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_a_product_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);

        $response = $this->actingAs($admin)->post('/products', [
            'category_id' => $category->id,
            'name' => 'Kopi Susu',
            'sku' => 'IMG-001',
            'price' => 12000,
            'stock' => 20,
            'image' => UploadedFile::fake()->image('kopi.jpg'),
        ]);

        $response->assertRedirect(route('products.index'));
        $product = Product::where('sku', 'IMG-001')->firstOrFail();
        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
        $this->assertNotNull($product->image_url);
    }

    public function test_replacing_image_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kopi Susu',
            'sku' => 'IMG-002',
            'price' => 12000,
            'stock' => 20,
            'image' => 'products/old.jpg',
        ]);
        Storage::disk('public')->put('products/old.jpg', 'fake-old-content');

        $this->actingAs($admin)->put("/products/{$product->id}", [
            'category_id' => $category->id,
            'name' => 'Kopi Susu',
            'sku' => 'IMG-002',
            'price' => 12000,
            'stock' => 20,
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('new.jpg'),
        ]);

        Storage::disk('public')->assertMissing('products/old.jpg');
        $this->assertNotEquals('products/old.jpg', $product->fresh()->image);
    }

    public function test_product_without_image_has_no_image_url(): void
    {
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'IMG-003',
            'price' => 5000,
            'stock' => 10,
        ]);

        $this->assertNull($product->image_url);
    }
}
