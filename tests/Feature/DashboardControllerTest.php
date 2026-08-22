<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(User $user, Product $product, int $qty, string $status = 'selesai', ?Carbon $createdAt = null): Transaction
    {
        $subtotal = $product->price * $qty;

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'invoice_no' => 'INV-' . uniqid(),
            'total' => $subtotal,
            'payment_method' => 'tunai',
            'amount_paid' => $subtotal,
            'change_due' => 0,
            'status' => $status,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'price' => $product->price,
            'subtotal' => $subtotal,
        ]);

        if ($createdAt) {
            $transaction->created_at = $createdAt;
            $transaction->save();
        }

        return $transaction;
    }

    private function makeProduct(int $stock = 50): Product
    {
        $category = Category::create(['name' => 'Minuman']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'DSH-' . uniqid(),
            'price' => 10000,
            'stock' => $stock,
        ]);
    }

    public function test_today_total_and_count_exclude_voided_transactions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $this->makeTransaction($admin, $product, 2, 'selesai', today());
        $this->makeTransaction($admin, $product, 5, 'dibatalkan', today());

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('todayTotal', 20000);
        $response->assertViewHas('todayCount', 1);
    }

    public function test_admin_sees_all_cashiers_today_total_but_kasir_sees_only_own(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kasirA = User::factory()->create(['role' => 'kasir']);
        $kasirB = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();

        $this->makeTransaction($kasirA, $product, 1, 'selesai', today());
        $this->makeTransaction($kasirB, $product, 1, 'selesai', today());

        $adminResponse = $this->actingAs($admin)->get('/dashboard');
        $adminResponse->assertViewHas('todayTotal', 20000);
        $adminResponse->assertViewHas('todayCount', 2);

        $kasirResponse = $this->actingAs($kasirA)->get('/dashboard');
        $kasirResponse->assertViewHas('todayTotal', 10000);
        $kasirResponse->assertViewHas('todayCount', 1);
    }

    public function test_low_stock_list_contains_only_products_under_threshold(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeProduct(5);
        $this->makeProduct(50);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('lowStock', function ($lowStock) {
            return $lowStock->count() === 1 && $lowStock->first()->stock === 5;
        });
    }

    public function test_seven_day_series_sums_totals_per_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $this->makeTransaction($admin, $product, 1, 'selesai', today());
        $this->makeTransaction($admin, $product, 1, 'selesai', today()->subDays(2));

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('weekSeries', function ($series) {
            return $series->count() === 7 && $series->sum('total') === 20000;
        });
    }
}
