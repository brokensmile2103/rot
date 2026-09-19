<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('closing_cash_expected', 12, 2)->nullable();
            $table->decimal('closing_cash_actual', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['expense', 'deposit', 'withdrawal']);
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_adjustments');
        Schema::dropIfExists('shifts');
    }
};
