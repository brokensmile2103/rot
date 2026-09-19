<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('bank_bin', 10)->nullable()->after('receipt_enabled');
            $table->string('bank_account_no', 30)->nullable()->after('bank_bin');
            $table->string('bank_account_name', 100)->nullable()->after('bank_account_no');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['bank_bin', 'bank_account_no', 'bank_account_name']);
        });
    }
};
