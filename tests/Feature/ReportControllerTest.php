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

class ReportControllerTest extends TestCase
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

    private function makeProduct(?Category $category = null): Product
    {
        $category ??= Category::create(['name' => 'Minuman']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'RPT-' . uniqid(),
            'price' => 10000,
            'stock' => 50,
        ]);
    }

    public function test_report_excludes_voided_transactions_and_respects_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $this->makeTransaction($admin, $product, 2, 'selesai', today());
        $this->makeTransaction($admin, $product, 3, 'dibatalkan', today());
        $this->makeTransaction($admin, $product, 1, 'selesai', today()->subDays(10));

        $response = $this->actingAs($admin)->get('/reports?from=' . today()->format('Y-m-d') . '&to=' . today()->format('Y-m-d'));

        $response->assertOk();
        // Only the completed, in-range transaction (qty 2 * 10000 = 20000) should count.
        $response->assertViewHas('totalPenjualan', 20000);
        $response->assertViewHas('jumlahTransaksi', 1);
    }

    public function test_inverted_date_range_flashes_error_and_falls_back_to_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/reports?from=2026-01-10&to=2026-01-01');

        $response->assertOk();
        $response->assertSessionHas('error');
        $response->assertViewHas('from', fn ($from) => $from->isSameDay(today()->startOfMonth()));
        $response->assertViewHas('to', fn ($to) => $to->isSameDay(today()));
    }

    public function test_category_and_cashier_breakdowns_are_correct(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Satu']);
        $kasir = User::factory()->create(['role' => 'kasir', 'name' => 'Kasir Dua']);

        $minuman = Category::create(['name' => 'Minuman']);
        $makanan = Category::create(['name' => 'Makanan']);
        $drink = $this->makeProduct($minuman);
        $food = $this->makeProduct($makanan);

        $this->makeTransaction($admin, $drink, 2, 'selesai', today());
        $this->makeTransaction($kasir, $food, 1, 'selesai', today());

        $response = $this->actingAs($admin)->get('/reports');

        $response->assertOk();
        $response->assertViewHas('categoryBreakdown', function ($breakdown) {
            return $breakdown->count() === 2;
        });
        $response->assertViewHas('cashierBreakdown', function ($breakdown) {
            return $breakdown->count() === 2;
        });
    }

    public function test_csv_export_has_correct_headers_and_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();
        $this->makeTransaction($admin, $product, 1, 'selesai', today());

        $response = $this->actingAs($admin)->get('/reports/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Invoice,Tanggal,Kasir,Total', $content);
    }

    public function test_pdf_export_returns_a_valid_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();
        $this->makeTransaction($admin, $product, 1, 'selesai', today());

        $response = $this->actingAs($admin)->get('/reports/export-pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_csv_import_creates_products_and_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $csv = "category,name,sku,price,stock\n";
        $csv .= "Minuman,Teh Botol,IMP-001,5000,20\n";

        $file = \Illuminate\Http\Testing\File::createWithContent('import.csv', $csv);

        $response = $this->actingAs($admin)->post('/reports/import', ['file' => $file]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'Minuman']);
        $this->assertDatabaseHas('products', ['sku' => 'IMP-001', 'name' => 'Teh Botol', 'price' => 5000, 'stock' => 20]);
    }

    public function test_kasir_cannot_access_reports(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)->get('/reports')->assertForbidden();
    }

    public function test_year_and_month_filter_resolves_to_that_calendar_month(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $this->makeTransaction($admin, $product, 1, 'selesai', Carbon::create(2026, 3, 15));
        $this->makeTransaction($admin, $product, 2, 'selesai', Carbon::create(2026, 4, 1)); // outside the filtered month

        $response = $this->actingAs($admin)->get('/reports?year=2026&month=3');

        $response->assertOk();
        $response->assertViewHas('from', fn ($from) => $from->isSameDay(Carbon::create(2026, 3, 1)));
        $response->assertViewHas('to', fn ($to) => $to->isSameDay(Carbon::create(2026, 3, 31)));
        $response->assertViewHas('jumlahTransaksi', 1);
        $response->assertViewHas('granularity', 'day');
    }

    public function test_year_only_filter_resolves_to_the_whole_year_with_monthly_granularity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $this->makeTransaction($admin, $product, 1, 'selesai', Carbon::create(2026, 1, 10));
        $this->makeTransaction($admin, $product, 1, 'selesai', Carbon::create(2026, 6, 10));
        $this->makeTransaction($admin, $product, 1, 'selesai', Carbon::create(2025, 12, 31)); // outside the filtered year

        $response = $this->actingAs($admin)->get('/reports?year=2026');

        $response->assertOk();
        $response->assertViewHas('from', fn ($from) => $from->isSameDay(Carbon::create(2026, 1, 1)));
        $response->assertViewHas('to', fn ($to) => $to->isSameDay(Carbon::create(2026, 12, 31)));
        $response->assertViewHas('jumlahTransaksi', 2);
        $response->assertViewHas('granularity', 'month');
        $response->assertViewHas('periodBreakdown', fn ($breakdown) => $breakdown->count() === 2);
    }

    public function test_bare_request_still_defaults_to_month_to_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/reports');

        $response->assertOk();
        $response->assertViewHas('from', fn ($from) => $from->isSameDay(today()->startOfMonth()));
        $response->assertViewHas('to', fn ($to) => $to->isSameDay(today()));
    }

    public function test_custom_range_overrides_year_and_month_when_both_are_submitted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        $this->makeTransaction($admin, $product, 1, 'selesai', Carbon::create(2026, 5, 5));

        // A leftover year=2026&month=3 alongside an explicit from/to (the "Rentang khusus"
        // path) — the custom range must win, not the month/year selects.
        $response = $this->actingAs($admin)->get('/reports?year=2026&month=3&from=2026-05-01&to=2026-05-31');

        $response->assertOk();
        $response->assertViewHas('from', fn ($from) => $from->isSameDay(Carbon::create(2026, 5, 1)));
        $response->assertViewHas('to', fn ($to) => $to->isSameDay(Carbon::create(2026, 5, 31)));
        $response->assertViewHas('jumlahTransaksi', 1);
    }

    public function test_transaction_list_is_paginated_but_exports_include_the_full_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct();

        foreach (range(1, 20) as $i) {
            $this->makeTransaction($admin, $product, 1, 'selesai', today());
        }

        $response = $this->actingAs($admin)->get('/reports');
        $response->assertOk();
        $response->assertViewHas('transactionsPage', fn ($page) => $page->count() === 15 && $page->total() === 20);
        $response->assertViewHas('jumlahTransaksi', 20); // the full-range aggregate, unaffected by pagination

        $page2 = $this->actingAs($admin)->get('/reports?page=2');
        $page2->assertOk();
        $page2->assertViewHas('transactionsPage', fn ($page) => $page->count() === 5);

        $csv = $this->actingAs($admin)->get('/reports/export');
        $csv->assertOk();
        $this->assertCount(21, explode("\n", trim($csv->streamedContent()))); // header + all 20 rows
    }
}
