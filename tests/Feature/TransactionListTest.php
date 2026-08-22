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

class TransactionListTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(User $user, Product $product, int $total, string $status = 'selesai', ?Carbon $createdAt = null): Transaction
    {
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'invoice_no' => 'INV-' . uniqid(),
            'total' => $total,
            'payment_method' => 'tunai',
            'amount_paid' => $total,
            'change_due' => 0,
            'status' => $status,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => $total,
            'subtotal' => $total,
        ]);

        if ($createdAt) {
            $transaction->created_at = $createdAt;
            $transaction->save();
        }

        return $transaction;
    }

    private function makeProduct(): Product
    {
        $category = Category::create(['name' => 'Minuman']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'LIST-' . uniqid(),
            'price' => 5000,
            'stock' => 50,
        ]);
    }

    public function test_from_to_range_filters_transactions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $inRange = $this->makeTransaction($admin, $product, 10000, 'selesai', Carbon::create(2026, 3, 15));
        $this->makeTransaction($admin, $product, 20000, 'selesai', Carbon::create(2026, 1, 1)); // outside

        $response = $this->actingAs($admin)->get('/transactions?from=2026-03-01&to=2026-03-31');

        $response->assertOk()
            ->assertSee($inRange->invoice_no)
            ->assertViewHas('transactions', fn ($page) => $page->total() === 1);
    }

    public function test_status_filter_isolates_voided_transactions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $voided = $this->makeTransaction($admin, $product, 10000, 'dibatalkan');
        $completed = $this->makeTransaction($admin, $product, 20000, 'selesai');

        $response = $this->actingAs($admin)->get('/transactions?status=dibatalkan');

        $response->assertOk()
            ->assertSee($voided->invoice_no)
            ->assertDontSee($completed->invoice_no);
    }

    public function test_sorting_by_total_ascending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $this->makeTransaction($admin, $product, 30000);
        $this->makeTransaction($admin, $product, 10000);
        $this->makeTransaction($admin, $product, 20000);

        $response = $this->actingAs($admin)->get('/transactions?sort=total&direction=asc');

        $response->assertOk();
        $response->assertViewHas('transactions', function ($page) {
            $totals = $page->pluck('total')->all();

            return $totals === [10000, 20000, 30000];
        });
    }

    public function test_admin_sees_batalkan_action_kasir_does_not(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct();
        $transaction = $this->makeTransaction($admin, $product, 10000);

        $adminView = $this->actingAs($admin)->get('/transactions');
        $adminView->assertOk()->assertSee('Batalkan');

        $kasirView = $this->actingAs($kasir)->get('/transactions');
        $kasirView->assertOk()->assertDontSee('Batalkan');
    }

    public function test_void_can_be_triggered_from_the_list_page_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();
        $transaction = $this->makeTransaction($admin, $product, 10000);

        // Same route the list page's modal submits to — confirms the new trigger point
        // (row action instead of the detail page) still works correctly end to end.
        $response = $this->actingAs($admin)->patch("/transactions/{$transaction->id}/void", [
            'reason' => 'Dibatalkan dari daftar',
        ]);

        $response->assertRedirect();
        $this->assertSame('dibatalkan', $transaction->fresh()->status);
    }

    public function test_year_and_month_filter_resolves_to_that_calendar_month(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $inMonth = $this->makeTransaction($admin, $product, 10000, 'selesai', Carbon::create(2026, 3, 15));
        $this->makeTransaction($admin, $product, 20000, 'selesai', Carbon::create(2026, 4, 1)); // outside

        $response = $this->actingAs($admin)->get('/transactions?year=2026&month=3');

        $response->assertOk()
            ->assertSee($inMonth->invoice_no)
            ->assertViewHas('transactions', fn ($page) => $page->total() === 1);
    }

    public function test_year_only_filter_resolves_to_the_whole_year(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $this->makeTransaction($admin, $product, 10000, 'selesai', Carbon::create(2026, 1, 10));
        $this->makeTransaction($admin, $product, 10000, 'selesai', Carbon::create(2026, 11, 20));
        $this->makeTransaction($admin, $product, 10000, 'selesai', Carbon::create(2025, 12, 31)); // outside

        $response = $this->actingAs($admin)->get('/transactions?year=2026');

        $response->assertOk();
        $response->assertViewHas('transactions', fn ($page) => $page->total() === 2);
    }

    public function test_bare_request_still_shows_everything_unfiltered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $this->makeTransaction($admin, $product, 10000, 'selesai', Carbon::create(2023, 9, 5));
        $this->makeTransaction($admin, $product, 10000, 'selesai', Carbon::create(2026, 6, 1));

        $response = $this->actingAs($admin)->get('/transactions');

        $response->assertOk();
        $response->assertViewHas('from', fn ($from) => $from === null);
        $response->assertViewHas('to', fn ($to) => $to === null);
        $response->assertViewHas('transactions', fn ($page) => $page->total() === 2);
    }

    public function test_custom_range_overrides_year_and_month_when_both_are_submitted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $inRange = $this->makeTransaction($admin, $product, 10000, 'selesai', Carbon::create(2026, 5, 5));

        // A leftover year=2026&month=3 alongside an explicit from/to (the "Rentang khusus"
        // path) — the custom range must win, not the month/year selects.
        $response = $this->actingAs($admin)->get('/transactions?year=2026&month=3&from=2026-05-01&to=2026-05-31');

        $response->assertOk()
            ->assertSee($inRange->invoice_no)
            ->assertViewHas('transactions', fn ($page) => $page->total() === 1);
    }
}
