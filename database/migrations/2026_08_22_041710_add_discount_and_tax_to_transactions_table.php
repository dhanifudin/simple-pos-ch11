<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Stored as the actual nominal amounts applied at sale time — not
            // recomputed later, so a receipt stays accurate even if the shop's
            // tax_percent setting changes afterward.
            $table->unsignedInteger('discount')->default(0)->after('total');
            $table->unsignedInteger('tax')->default(0)->after('discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['discount', 'tax']);
        });
    }
};
