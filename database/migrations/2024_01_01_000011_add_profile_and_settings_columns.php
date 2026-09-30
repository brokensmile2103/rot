<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->after('name');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->string('font_scale', 10)->default('md')->after('address');
            $table->string('accent_color', 20)->default('amber')->after('font_scale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_url');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['font_scale', 'accent_color']);
        });
    }
};
