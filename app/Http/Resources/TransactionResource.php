<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'kasir' => $this->user->name,
            'total' => $this->total,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'amount_paid' => $this->amount_paid,
            'change_due' => $this->change_due,
            'created_at' => $this->created_at->toIso8601String(),
            'items' => $this->whenLoaded('details', fn () => $this->details->map(fn ($d) => [
                'product' => $d->product->name,
                'qty' => $d->qty,
                'price' => $d->price,
                'subtotal' => $d->subtotal,
            ])),
        ];
    }
}
