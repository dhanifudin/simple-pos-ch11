<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $sku): Product
    {
        $category = Category::create(['name' => 'Minuman']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Produk ' . $sku,
            'sku' => $sku,
            'price' => 10000,
            'stock' => 10,
        ]);
    }

    public function test_admin_can_bulk_activate_and_deactivate_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $p1 = $this->makeProduct('BULK-001');
        $p2 = $this->makeProduct('BULK-002');

        $response = $this->actingAs($admin)->patch('/products/bulk-status', [
            'ids' => [$p1->id, $p2->id],
            'is_active' => 0,
        ]);

        $response->assertRedirect();
        $this->assertFalse($p1->fresh()->is_active);
        $this->assertFalse($p2->fresh()->is_active);

        $this->actingAs($admin)->patch('/products/bulk-status', [
            'ids' => [$p1->id, $p2->id],
            'is_active' => 1,
        ]);

        $this->assertTrue($p1->fresh()->is_active);
        $this->assertTrue($p2->fresh()->is_active);
    }

    public function test_admin_can_bulk_deactivate_kasir_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kasirA = User::factory()->create(['role' => 'kasir']);
        $kasirB = User::factory()->create(['role' => 'kasir']);

        $response = $this->actingAs($admin)->patch('/users/bulk-status', [
            'ids' => [$kasirA->id, $kasirB->id],
            'is_active' => 0,
        ]);

        $response->assertRedirect();
        $this->assertFalse($kasirA->fresh()->is_active);
        $this->assertFalse($kasirB->fresh()->is_active);
    }

    public function test_bulk_deactivate_that_would_zero_out_active_admins_is_rejected_entirely(): void
    {
        // Only two admins exist; deactivating both — including self — would leave
        // zero active admins. The self-guard fires first, but the net effect is the
        // same invariant: this batch must be rejected in full, nothing partially applied.
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $kasir = User::factory()->create(['role' => 'kasir']);

        $response = $this->actingAs($admin)->patch('/users/bulk-status', [
            'ids' => [$admin->id, $otherAdmin->id, $kasir->id],
            'is_active' => 0,
        ]);

        $response->assertSessionHas('error');
        // None of the batch applied — not even the kasir, which alone would've been fine.
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($otherAdmin->fresh()->is_active);
        $this->assertTrue($kasir->fresh()->is_active);
    }

    public function test_bulk_deactivate_of_all_other_admins_by_a_third_admin_succeeds(): void
    {
        // A third acting admin (not in the batch) means at least one active admin
        // always remains after the batch applies — a legitimate, non-zeroing action.
        $actingAdmin = User::factory()->create(['role' => 'admin']);
        $admin1 = User::factory()->create(['role' => 'admin']);
        $admin2 = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($actingAdmin)->patch('/users/bulk-status', [
            'ids' => [$admin1->id, $admin2->id],
            'is_active' => 0,
        ]);

        $response->assertSessionHas('status');
        $this->assertFalse($admin1->fresh()->is_active);
        $this->assertFalse($admin2->fresh()->is_active);
        $this->assertTrue($actingAdmin->fresh()->is_active);
    }

    public function test_bulk_delete_categories_skips_ones_with_products_and_deletes_the_rest(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $empty = Category::create(['name' => 'Kosong']);
        $withProducts = Category::create(['name' => 'Terisi']);
        Product::create([
            'category_id' => $withProducts->id,
            'name' => 'Produk Terisi',
            'sku' => 'BULK-003',
            'price' => 10000,
            'stock' => 5,
        ]);

        $response = $this->actingAs($admin)->delete('/categories/bulk-delete', [
            'ids' => [$empty->id, $withProducts->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $empty->id]);
        $this->assertDatabaseHas('categories', ['id' => $withProducts->id]);
    }

    public function test_kasir_cannot_use_bulk_endpoints(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct('BULK-004');

        $this->actingAs($kasir)->patch('/products/bulk-status', ['ids' => [$product->id], 'is_active' => 0])
            ->assertForbidden();
        $this->actingAs($kasir)->patch('/users/bulk-status', ['ids' => [$kasir->id], 'is_active' => 0])
            ->assertForbidden();
        $this->actingAs($kasir)->delete('/categories/bulk-delete', ['ids' => [1]])
            ->assertForbidden();
    }
}
