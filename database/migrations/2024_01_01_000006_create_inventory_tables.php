<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit', 20);
            $table->decimal('current_stock', 12, 2)->default(0);
            $table->decimal('avg_cost_per_unit', 12, 4)->default(0);
            $table->decimal('low_stock_threshold', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->timestamps();
            $table->unique(['product_variant_id', 'ingredient_id']);
        });

        Schema::create('stock_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ins');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('ingredients');
    }
};
