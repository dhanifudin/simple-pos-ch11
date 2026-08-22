<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the database with enough data to demonstrate pagination and reporting at a
     * realistic scale, using bulk inserts (not per-row Eloquent create()) for the
     * transaction volume so seeding stays fast: a hand-curated ~39-item cafe/foodcourt
     * menu and ~2,500 transactions (~6,000+ line items).
     */
    public function run(): void
    {
        $start = microtime(true);

        $admin = User::factory()->create([
            'name' => 'Admin Kafe',
            'email' => 'admin@pos.test',
            'role' => 'admin',
        ]);

        $kasir = User::factory()->create([
            'name' => 'Kasir Kafe',
            'email' => 'kasir@pos.test',
            'role' => 'kasir',
        ]);

        $categoryNames = ['Makanan Berat', 'Mie & Pasta', 'Snack & Gorengan', 'Minuman Dingin', 'Minuman Panas', 'Dessert', 'Roti & Sarapan', 'Menu Spesial'];
        $categories = collect($categoryNames)->map(fn (string $name) => Category::create(['name' => $name]));
        $categoryIds = $categories->pluck('id')->all();

        $productIds = $this->seedProducts($categoryIds);
        [$transactionCount, $detailCount] = $this->seedTransactions([$admin->id, $kasir->id], $productIds);

        $elapsed = round(microtime(true) - $start, 2);

        $this->command?->info(sprintf(
            'Seeded: %d categories, %d products, %d transactions, %d line items in %ss.',
            count($categoryIds),
            count($productIds),
            $transactionCount,
            $detailCount,
            $elapsed
        ));
    }

    /**
     * A hand-authored, realistically-sized cafe/foodcourt menu (~39 items across the 8
     * categories from run() — a real kedai's menu board has dozens of items, not hundreds,
     * so this is a curated list rather than synthetically generated filler). Small enough
     * to insert in one statement, well under SQLite's ~999 bound-parameter limit. Three
     * items are deliberately low-stock (<10) so the dashboard's "stok menipis" widget has
     * something to show, and two are inactive (discontinued) so the products list
     * demonstrates the aktif/nonaktif toggle — they still have historical sales via
     * seedTransactions() below, which is exactly why products are deactivated rather than
     * deleted.
     *
     * @return int[] product IDs in insertion order
     */
    private function seedProducts(array $categoryIds): array
    {
        // [categoryIndex, name, sku, price, stock, isActive] — categoryIndex matches
        // $categoryNames's order in run() (0 = Makanan Berat, ... 7 = Menu Spesial).
        $menu = [
            [0, 'Nasi Goreng Spesial', 'NGS-001', 22000, 45, true],
            [0, 'Nasi Ayam Geprek', 'NAG-001', 20000, 38, true],
            [0, 'Nasi Rendang', 'NRD-001', 25000, 30, true],
            [0, 'Ayam Bakar Madu', 'ABM-001', 24000, 28, true],
            [0, 'Ikan Bakar Sambal Matah', 'IBS-001', 27000, 8, true],

            [1, 'Mie Ayam Bakso', 'MAB-001', 18000, 40, true],
            [1, 'Mie Goreng Jawa', 'MGJ-001', 17000, 35, true],
            [1, 'Mie Aceh', 'MAC-001', 19000, 25, true],
            [1, 'Spaghetti Aglio Olio', 'SAO-001', 23000, 20, true],
            [1, 'Kwetiau Goreng', 'KWT-001', 18000, 30, true],

            [2, 'Kentang Goreng', 'KTG-001', 12000, 50, true],
            [2, 'Cireng Isi Ayam', 'CRG-001', 9000, 55, true],
            [2, 'Tahu Crispy', 'THC-001', 8000, 45, true],
            [2, 'Pisang Goreng Keju', 'PGK-001', 10000, 40, true],
            [2, 'Tempe Mendoan', 'TPM-001', 7000, 6, true],

            [3, 'Es Teh Manis', 'ETM-001', 5000, 100, true],
            [3, 'Es Jeruk Peras', 'EJP-001', 8000, 70, true],
            [3, 'Es Kopi Susu Gula Aren', 'EKS-001', 15000, 60, true],
            [3, 'Es Cendol', 'ECD-001', 10000, 25, true],
            [3, 'Es Cokelat', 'ECK-001', 12000, 30, true],

            [4, 'Teh Tarik Panas', 'TTP-001', 9000, 60, true],
            [4, 'Kopi Hitam', 'KPH-001', 8000, 50, true],
            [4, 'Kopi Susu Panas', 'KSP-001', 14000, 45, true],
            [4, 'Wedang Jahe', 'WJH-001', 8000, 6, true],
            [4, 'Cappuccino', 'CAP-001', 18000, 25, true],

            [5, 'Pisang Coklat Keju', 'PCK-001', 12000, 20, false],
            [5, 'Puding Karamel', 'PDK-001', 10000, 22, true],
            [5, 'Waffle Coklat', 'WFC-001', 20000, 18, true],
            [5, 'Es Krim Vanilla', 'EKV-001', 9000, 30, true],
            [5, 'Banana Split', 'BSP-001', 18000, 15, true],

            [6, 'Roti Bakar Coklat Keju', 'RBK-001', 14000, 45, true],
            [6, 'Roti Bakar Selai Kacang', 'RSK-001', 13000, 40, true],
            [6, 'Sandwich Telur', 'SDT-001', 15000, 25, true],
            [6, 'Croissant Coklat', 'CRC-001', 16000, 20, true],
            [6, 'Omelette Sayur', 'OMS-001', 14000, 22, true],

            [7, 'Paket Hemat Nasi + Es Teh', 'PHT-001', 25000, 25, true],
            [7, 'Paket Mie + Gorengan', 'PMG-001', 24000, 20, true],
            [7, 'Menu Chef Recommended', 'MCR-001', 35000, 12, true],
            [7, 'Kopi Signature Kedai Rasa', 'KSR-001', 20000, 30, false],
        ];

        $now = now();
        $rows = [];

        foreach ($menu as [$categoryIndex, $name, $sku, $price, $stock, $isActive]) {
            $rows[] = [
                'category_id' => $categoryIds[$categoryIndex],
                'name' => $name,
                'sku' => $sku,
                'price' => $price,
                'stock' => $stock,
                'is_active' => $isActive,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('products')->insert($rows);

        return range(1, count($rows));
    }

    /**
     * Bulk-insert ~7,400 transactions (1-4 items each) with their line items, chunked at
     * 100 rows/insert per table. Spread across the last 36 months (3 years) so every month/
     * year combination in the /reports filter has real data — growth-weighted (a floor of 60
     * transactions in the oldest month, ramping up to ~350 in the current month) rather than
     * flat, so the reports trend chart actually shows something across different years instead
     * of identical bars everywhere. Wrapped in one DB transaction so the whole seed either
     * commits atomically or not at all.
     *
     * @return array{0:int,1:int} [transaction count, detail row count]
     */
    private function seedTransactions(array $userIds, array $productIds): array
    {
        // Snapshot product prices once (avoids thousands of individual lookups during generation).
        $prices = DB::table('products')->pluck('price', 'id');

        $now = now();
        $transactionRows = [];
        $detailRows = [];

        // monthsAgo => target transaction count for that month, oldest month first. A linear
        // ramp from a floor of 60 (3 years ago) to ~350 (this month) — not flat/uniform, so a
        // busy-growing-kedai story shows up in the reports trend chart.
        $monthsBack = 36;
        $monthCounts = [];
        for ($monthsAgo = $monthsBack - 1; $monthsAgo >= 0; $monthsAgo--) {
            $monthCounts[$monthsAgo] = 60 + (int) round(290 * ($monthsBack - 1 - $monthsAgo) / ($monthsBack - 1));
        }

        $t = 0;

        DB::transaction(function () use ($userIds, $productIds, $prices, $now, $monthCounts, &$transactionRows, &$detailRows, &$t) {
            foreach ($monthCounts as $monthsAgo => $count) {
                // subMonthsNoOverflow (not subMonths) — avoids day-of-month overflow across
                // months of different lengths (e.g. Jan 31 minus a month must land on Dec 31,
                // not roll into March), the same class of bug fixed in the reports month filter.
                $monthStart = $now->copy()->subMonthsNoOverflow($monthsAgo)->startOfMonth();
                // The current month is only partially elapsed — can't place transactions in
                // the future, so cap its valid days at today; every earlier month is complete.
                $lastValidDay = $monthsAgo === 0 ? $now->day : $monthStart->daysInMonth;

                for ($i = 0; $i < $count; $i++) {
                    $t++;
                    $day = ($i * 17 + $monthsAgo * 3) % $lastValidDay; // 0-indexed
                    $createdAt = $monthStart->copy()->addDays($day)->addMinutes(($t * 7) % 1440);

                    $itemCount = ($t % 4) + 1; // 1-4 items per transaction
                    $offset = ($t * 31) % count($productIds);
                    $total = 0;
                    $items = [];

                    for ($n = 0; $n < $itemCount; $n++) {
                        $productId = $productIds[($offset + $n * 17) % count($productIds)];
                        $qty = (($t + $n) % 5) + 1; // 1-5
                        $price = $prices[$productId];
                        $subtotal = $price * $qty;
                        $total += $subtotal;

                        $items[] = [
                            'product_id' => $productId,
                            'qty' => $qty,
                            'price' => $price,
                            'subtotal' => $subtotal,
                        ];
                    }

                    // Cash-only: a plausible tendered amount (rounded up to a bill
                    // denomination, with a bit of variation) so change_due isn't always zero.
                    $amountPaid = (int) (ceil($total / 5000) * 5000) + (($t % 3) * 5000);

                    $transactionRows[] = [
                        'user_id' => $userIds[$t % count($userIds)],
                        'invoice_no' => 'INV-' . $createdAt->format('Ymd') . '-' . str_pad((string) $t, 6, '0', STR_PAD_LEFT),
                        'total' => $total,
                        'payment_method' => 'tunai',
                        'amount_paid' => $amountPaid,
                        'change_due' => $amountPaid - $total,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];

                    foreach ($items as $item) {
                        // transaction_id resolved after insert (see below) — placeholder index kept via array position.
                        $detailRows[] = $item + ['__transaction_index' => $t - 1, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                    }
                }
            }

            foreach (array_chunk($transactionRows, 100) as $chunk) {
                DB::table('transactions')->insert($chunk);
            }
        });

        // transactions were inserted in order on an empty table, so IDs are sequential from 1.
        $transactionIds = range(1, count($transactionRows));

        foreach ($detailRows as &$row) {
            $row['transaction_id'] = $transactionIds[$row['__transaction_index']];
            unset($row['__transaction_index']);
        }
        unset($row);

        foreach (array_chunk($detailRows, 100) as $chunk) {
            DB::table('transaction_details')->insert($chunk);
        }

        return [count($transactionRows), count($detailRows)];
    }
}
