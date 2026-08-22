<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionVoidTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kasir = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Air Mineral',
            'sku' => 'VOID-001',
            'price' => 5000,
            'stock' => 10,
        ]);

        $this->actingAs($kasir)->post('/pos', [
            'items' => [['product_id' => $product->id, 'qty' => 3]],
        ]);

        $transaction = Transaction::latest()->first();

        return [$admin, $kasir, $product, $transaction];
    }

    public function test_admin_can_void_a_transaction_and_stock_is_restored(): void
    {
        [$admin, , $product, $transaction] = $this->makeTransaction();
        $this->assertEquals(7, $product->fresh()->stock);

        $response = $this->actingAs($admin)->patch("/transactions/{$transaction->id}/void", [
            'reason' => 'Salah input pesanan',
        ]);

        $response->assertRedirect();
        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertEquals('dibatalkan', $transaction->fresh()->status);
        $this->assertEquals($admin->id, $transaction->fresh()->voided_by);
    }

    public function test_voiding_twice_is_rejected_and_does_not_double_restore_stock(): void
    {
        [$admin, , $product, $transaction] = $this->makeTransaction();

        $this->actingAs($admin)->patch("/transactions/{$transaction->id}/void", ['reason' => 'Pertama']);
        $this->assertEquals(10, $product->fresh()->stock);

        $response = $this->actingAs($admin)->patch("/transactions/{$transaction->id}/void", ['reason' => 'Kedua']);

        $response->assertSessionHas('error');
        $this->assertEquals(10, $product->fresh()->stock); // unchanged, not double-restored
    }

    public function test_kasir_cannot_void_a_transaction(): void
    {
        [, $kasir, , $transaction] = $this->makeTransaction();

        $this->actingAs($kasir)->patch("/transactions/{$transaction->id}/void", ['reason' => 'Coba-coba'])
            ->assertForbidden();
    }

    public function test_voided_transaction_excluded_from_dashboard_and_report_totals(): void
    {
        [$admin, , , $transaction] = $this->makeTransaction();

        $this->actingAs($admin)->patch("/transactions/{$transaction->id}/void", ['reason' => 'Batal']);

        $dashboard = $this->actingAs($admin)->get('/dashboard');
        $dashboard->assertOk();
        $dashboard->assertSee('Rp 0'); // today's total excludes the voided sale

        $report = $this->actingAs($admin)->get('/reports');
        $report->assertOk();
        $report->assertDontSee($transaction->invoice_no);
    }
}
