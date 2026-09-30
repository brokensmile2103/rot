<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('einvoice_enabled')->default(false)->after('points_redeem_value');
            $table->boolean('einvoice_sandbox')->default(true)->after('einvoice_enabled');
            $table->string('einvoice_client_id')->nullable()->after('einvoice_sandbox');
            $table->text('einvoice_client_secret')->nullable()->after('einvoice_client_id'); // mã hoá bằng Eloquent cast
            $table->string('einvoice_provider_account_id')->nullable()->after('einvoice_client_secret');
            $table->string('einvoice_template_code')->nullable()->after('einvoice_provider_account_id');
            $table->string('einvoice_invoice_series')->nullable()->after('einvoice_template_code');
            // Cache token để không phải xin token mới cho mỗi hoá đơn (token sống 24h).
            $table->text('einvoice_access_token')->nullable()->after('einvoice_invoice_series');
            $table->timestamp('einvoice_token_expires_at')->nullable()->after('einvoice_access_token');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('einvoice_status', 20)->nullable()->after('points_redeemed_value'); // null/pending/issued/failed
            $table->string('einvoice_tracking_code')->nullable()->after('einvoice_status');
            $table->string('einvoice_reference_code')->nullable()->after('einvoice_tracking_code');
            $table->string('einvoice_pdf_url')->nullable()->after('einvoice_reference_code');
            $table->text('einvoice_error')->nullable()->after('einvoice_pdf_url');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['einvoice_status', 'einvoice_tracking_code', 'einvoice_reference_code', 'einvoice_pdf_url', 'einvoice_error']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'einvoice_enabled', 'einvoice_sandbox', 'einvoice_client_id', 'einvoice_client_secret',
                'einvoice_provider_account_id', 'einvoice_template_code', 'einvoice_invoice_series',
                'einvoice_access_token', 'einvoice_token_expires_at',
            ]);
        });
    }
};
