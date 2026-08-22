<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $category = Category::create(['name' => 'Minuman']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Teh Botol Sosro',
            'sku' => 'SRCH-001',
            'price' => 5000,
            'stock' => 10,
        ]);
    }

    private function makeTransaction(User $user, Product $product): Transaction
    {
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'invoice_no' => 'INV-SEARCHABLE-001',
            'total' => $product->price,
            'payment_method' => 'tunai',
            'amount_paid' => $product->price,
            'change_due' => 0,
            'status' => 'selesai',
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => $product->price,
            'subtotal' => $product->price,
        ]);

        return $transaction;
    }

    public function test_search_matches_products_and_transactions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();
        $this->makeTransaction($admin, $product);

        $response = $this->actingAs($admin)->get('/search?q=Teh Botol');
        $response->assertOk();
        $response->assertViewHas('products', fn ($products) => $products->count() === 1);

        $response = $this->actingAs($admin)->get('/search?q=SEARCHABLE');
        $response->assertOk();
        $response->assertViewHas('transactions', fn ($transactions) => $transactions->count() === 1);
    }

    public function test_kasir_does_not_see_user_results(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir', 'name' => 'Kasir Findable']);

        $response = $this->actingAs($kasir)->get('/search?q=Findable');

        $response->assertOk();
        $response->assertViewHas('users', fn ($users) => $users->isEmpty());
    }

    public function test_admin_sees_user_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'kasir', 'name' => 'Kasir Findable']);

        $response = $this->actingAs($admin)->get('/search?q=Findable');

        $response->assertOk();
        $response->assertViewHas('users', fn ($users) => $users->count() === 1);
    }

    public function test_empty_query_is_handled_gracefully(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/search');

        $response->assertOk();
        $response->assertViewHas('products', fn ($products) => $products->isEmpty());
    }

    public function test_kasir_transaction_search_only_sees_own_transactions(): void
    {
        $kasirA = User::factory()->create(['role' => 'kasir']);
        $kasirB = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();
        $this->makeTransaction($kasirB, $product);

        $response = $this->actingAs($kasirA)->get('/search?q=SEARCHABLE');

        $response->assertOk();
        $response->assertViewHas('transactions', fn ($transactions) => $transactions->isEmpty());
    }
}
