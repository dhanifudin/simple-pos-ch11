<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\ShopSetting;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $transactions = Transaction::with('user')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return TransactionResource::collection($transactions);
    }

    /**
     * Buat transaksi dari aplikasi kasir mobile (token-based API authentication, minggu 10).
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $items = $request->validated()['items'];
        $amountPaidInput = $request->filled('amount_paid') ? (int) $request->input('amount_paid') : null;
        $discountInput = $request->filled('discount') ? (int) $request->input('discount') : 0;
        $taxPercent = ShopSetting::current()->tax_percent;

        $transaction = DB::transaction(function () use ($items, $amountPaidInput, $discountInput, $taxPercent) {
            $itemsSubtotal = 0;
            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'invoice_no' => 'INV-API-' . now()->format('Ymd-His') . '-' . random_int(100, 999),
                'total' => 0,
            ]);

            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                abort_if($product->stock < $item['qty'], 422, "Stok {$product->name} tidak mencukupi.");

                $subtotal = $product->price * $item['qty'];
                $itemsSubtotal += $subtotal;

                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'qty' => $item['qty'],
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                ]);

                $product->decrement('stock', $item['qty']);
            }

            $discount = min($discountInput, $itemsSubtotal);
            $tax = (int) round(($itemsSubtotal - $discount) * $taxPercent / 100);
            $grandTotal = $itemsSubtotal - $discount + $tax;

            $amountPaid = $amountPaidInput ?? $grandTotal;
            abort_if($amountPaid < $grandTotal, 422, 'Uang diterima kurang dari total transaksi.');

            return tap($transaction)->update([
                'total' => $grandTotal,
                'discount' => $discount,
                'tax' => $tax,
                'payment_method' => 'tunai',
                'amount_paid' => $amountPaid,
                'change_due' => $amountPaid - $grandTotal,
            ]);
        });

        return (new TransactionResource($transaction->load('details.product', 'user')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Transaction $transaction): TransactionResource
    {
        abort_if($transaction->user_id !== Auth::id(), 403);

        return new TransactionResource($transaction->load('details.product', 'user'));
    }
}
