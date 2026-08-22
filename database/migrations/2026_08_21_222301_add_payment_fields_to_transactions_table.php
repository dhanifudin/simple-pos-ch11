<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_method')->default('tunai')->after('total'); // tunai | qris | kartu
            $table->unsignedInteger('amount_paid')->nullable()->after('payment_method');
            $table->unsignedInteger('change_due')->default(0)->after('amount_paid');
        });

        // amount_paid has no fixed default (it must equal each row's own total), so
        // existing rows need an explicit backfill; payment_method/change_due already
        // got their column default applied to existing rows by ALTER TABLE.
        DB::statement('UPDATE transactions SET amount_paid = total WHERE amount_paid IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'amount_paid', 'change_due']);
        });
    }
};
