<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$transactions, $from, $to] = $this->rangeQuery($request);

        $totalPenjualan = $transactions->sum('total');
        $jumlahTransaksi = $transactions->count();
        $avgTransaksi = $jumlahTransaksi > 0 ? intdiv($totalPenjualan, $jumlahTransaksi) : 0;

        // A range over a month rolls the trend up to monthly bars instead of daily —
        // a full year at day-level would be ~365 rows/bars, not remotely scannable.
        $granularity = $from->diffInDays($to) > 31 ? 'month' : 'day';
        $periodBreakdown = $this->periodBreakdown($transactions, $granularity);

        $topProducts = $this->productBreakdownQuery($from, $to)->limit(5)->get();

        $categoryBreakdown = DB::table('transaction_details')
            ->join('products', 'products.id', '=', 'transaction_details.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->whereBetween('transactions.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('transactions.status', 'selesai')
            ->selectRaw('categories.name, sum(transaction_details.qty) as qty_terjual, sum(transaction_details.subtotal) as total_penjualan')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_penjualan')
            ->get();

        // Reuses $transactions (already eager-loaded with `user`) instead of a new query.
        $cashierBreakdown = $transactions
            ->groupBy('user_id')
            ->map(fn ($group) => [
                'name' => $group->first()->user->name,
                'total' => $group->sum('total'),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        // The on-screen "Daftar Transaksi" list is paginated (never dump the whole range at
        // once) — a separate query from the full $transactions collection above, which the
        // KPI sums/breakdowns genuinely need in full. CSV/PDF exports intentionally keep using
        // the unpaginated collection — an export should contain everything in range.
        $transactionsPage = $this->transactionsQuery($from, $to)->paginate(15)->withQueryString();

        // Pre-fills the month/year quick-filter selects, reflecting whatever the request
        // actually asked for (or the resolved default range when nothing was submitted).
        $selectedYear = $request->filled('year') ? (int) $request->input('year') : $from->year;
        $selectedMonth = $request->filled('month')
            ? (int) $request->input('month')
            : ($request->filled('year') ? null : $from->month);
        $availableYears = range(now()->year, now()->year - 2);

        return view('reports.index', compact(
            'transactions', 'totalPenjualan', 'jumlahTransaksi', 'avgTransaksi', 'from', 'to',
            'periodBreakdown', 'granularity', 'topProducts', 'categoryBreakdown', 'cashierBreakdown',
            'transactionsPage', 'selectedYear', 'selectedMonth', 'availableYears'
        ));
    }

    /**
     * Query rentang tanggal bersama untuk index/export/PDF (menghindari duplikasi filter).
     */
    private function rangeQuery(Request $request): array
    {
        [$from, $to] = $this->resolveRange($request);

        $transactions = $this->transactionsQuery($from, $to)->get();

        return [$transactions, $from, $to];
    }

    /**
     * Resolves the filter range from the request: an explicit from/to always wins (the
     * "Rentang khusus" advanced path); otherwise year+month resolves to that calendar month,
     * year alone to that whole calendar year; with none of those present at all (a bare
     * /reports request), falls back to the existing month-to-date default.
     */
    private function resolveRange(Request $request): array
    {
        $from = $request->date('from');
        $to = $request->date('to');

        if (! $from && ! $to) {
            $year = $request->integer('year') ?: null;
            $month = $request->integer('month') ?: null;

            if ($year && $month) {
                $from = Carbon::create($year, $month, 1)->startOfMonth();
                $to = $from->copy()->endOfMonth();
            } elseif ($year) {
                $from = Carbon::create($year, 1, 1)->startOfYear();
                $to = Carbon::create($year, 12, 31)->endOfYear();
            }
        }

        $from ??= today()->startOfMonth();
        $to ??= today();

        // An inverted range (to before from) would otherwise silently produce an
        // empty-looking report indistinguishable from "no sales" — flash an error
        // and fall back to the default range instead of a confusing blank page.
        if ($to->lt($from)) {
            session()->flash('error', 'Rentang tanggal tidak valid ("Sampai" sebelum "Dari") — menampilkan rentang default.');
            $from = today()->startOfMonth();
            $to = today();
        }

        return [$from, $to];
    }

    /**
     * Voided sales are excluded from financial reporting entirely (unlike the general
     * /transactions history, which keeps them visible for audit trail).
     */
    private function transactionsQuery(Carbon $from, Carbon $to)
    {
        return Transaction::with('user')
            ->completed()
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->latest();
    }

    /**
     * Groups an already-fetched transaction collection into day- or month-keyed points for
     * the trend chart/table, sorted chronologically (oldest first — left-to-right on a chart).
     */
    private function periodBreakdown($transactions, string $granularity)
    {
        if ($granularity === 'day') {
            return $transactions
                ->groupBy(fn (Transaction $t) => $t->created_at->format('Y-m-d'))
                ->map(fn ($group, $key) => [
                    'label' => Carbon::parse($key)->translatedFormat('d M'),
                    'total' => $group->sum('total'),
                    'count' => $group->count(),
                ])
                ->sortKeys()
                ->values();
        }

        return $transactions
            ->groupBy(fn (Transaction $t) => $t->created_at->format('Y-m'))
            ->map(fn ($group, $key) => [
                'label' => Carbon::parse($key . '-01')->translatedFormat('M Y'),
                'total' => $group->sum('total'),
                'count' => $group->count(),
            ])
            ->sortKeys()
            ->values();
    }

    private function productBreakdownQuery($from, $to): \Illuminate\Database\Query\Builder
    {
        return DB::table('transaction_details')
            ->join('products', 'products.id', '=', 'transaction_details.product_id')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->whereBetween('transactions.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('transactions.status', 'selesai')
            ->selectRaw('products.name, sum(transaction_details.qty) as qty_terjual, sum(transaction_details.subtotal) as total_penjualan')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_penjualan');
    }

    /**
     * Ekspor laporan penjualan sebagai CSV (pengolahan & export data — minggu 9).
     */
    public function export(Request $request): StreamedResponse
    {
        [$transactions, $from, $to] = $this->rangeQuery($request);

        $filename = 'laporan-penjualan-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice', 'Tanggal', 'Kasir', 'Total']);

            foreach ($transactions as $t) {
                fputcsv($handle, [$t->invoice_no, $t->created_at->format('Y-m-d H:i'), $t->user->name, $t->total]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Ekspor laporan penjualan sebagai PDF (studi kasus DomPDF — minggu 9).
     */
    public function exportPdf(Request $request): Response
    {
        [$transactions, $from, $to] = $this->rangeQuery($request);

        $totalPenjualan = $transactions->sum('total');
        $jumlahTransaksi = $transactions->count();
        $avgTransaksi = $jumlahTransaksi > 0 ? intdiv($totalPenjualan, $jumlahTransaksi) : 0;

        $granularity = $from->diffInDays($to) > 31 ? 'month' : 'day';
        $periodBreakdown = $this->periodBreakdown($transactions, $granularity);

        $categoryBreakdown = DB::table('transaction_details')
            ->join('products', 'products.id', '=', 'transaction_details.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->whereBetween('transactions.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('transactions.status', 'selesai')
            ->selectRaw('categories.name, sum(transaction_details.qty) as qty_terjual, sum(transaction_details.subtotal) as total_penjualan')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_penjualan')
            ->get();

        $cashierBreakdown = $transactions
            ->groupBy('user_id')
            ->map(fn ($group) => [
                'name' => $group->first()->user->name,
                'total' => $group->sum('total'),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $filename = 'laporan-penjualan-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf';

        $pdf = Pdf::loadView('reports.pdf', compact(
            'transactions', 'totalPenjualan', 'jumlahTransaksi', 'avgTransaksi', 'from', 'to',
            'periodBreakdown', 'granularity', 'categoryBreakdown', 'cashierBreakdown'
        ))->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    /**
     * Import data produk dari CSV (kolom: category,name,sku,price,stock).
     */
    public function importForm(): View
    {
        return view('reports.import');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $imported = 0;

        DB::transaction(function () use ($handle, &$imported) {
            while (($row = fgetcsv($handle)) !== false) {
                [$categoryName, $name, $sku, $price, $stock] = $row;

                $category = Category::firstOrCreate(['name' => trim($categoryName)]);

                Product::updateOrCreate(
                    ['sku' => trim($sku)],
                    [
                        'category_id' => $category->id,
                        'name' => trim($name),
                        'price' => (int) $price,
                        'stock' => (int) $stock,
                    ]
                );

                $imported++;
            }
        });

        fclose($handle);

        return back()->with('status', "{$imported} produk berhasil diimpor.");
    }
}
