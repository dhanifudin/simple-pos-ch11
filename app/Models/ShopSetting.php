<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Singleton settings row (always exactly one, seeded by its migration) — drives the
 * shop name/address/phone/logo shown on the login page, sidebar, and receipt/report
 * PDFs, so renaming the shop no longer needs a code edit.
 */
class ShopSetting extends Model
{
    protected $table = 'shop_settings';

    protected $fillable = ['name', 'address', 'phone', 'logo', 'tax_percent'];

    protected function casts(): array
    {
        return [
            'tax_percent' => 'integer',
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->logo ? Storage::disk('public')->url($this->logo) : null,
        );
    }

    public static function current(): self
    {
        return static::first() ?? new static(['name' => 'POS UMKM']);
    }
}
