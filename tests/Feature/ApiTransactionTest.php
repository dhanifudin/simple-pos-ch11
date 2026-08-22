<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_token(): void
    {
        $user = User::factory()->create(['role' => 'kasir']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_products_endpoint_requires_token(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_authenticated_request_can_create_transaction(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['name' => 'Snack']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Keripik',
            'sku' => 'API-001',
            'price' => 8000,
            'stock' => 20,
        ]);
        $token = $kasir->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/transactions', [
                'items' => [['product_id' => $product->id, 'qty' => 2]],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.total', 16000);
        $this->assertEquals(18, $product->fresh()->stock);
    }
}
