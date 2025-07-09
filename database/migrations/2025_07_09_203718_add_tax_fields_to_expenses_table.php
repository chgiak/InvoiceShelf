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
        Schema::table('expenses', function (Blueprint $table) {
            // Add tax fields similar to invoices
            $table->unsignedBigInteger('tax')->default(0)->after('amount');
            $table->unsignedBigInteger('base_tax')->default(0)->after('base_amount');
            $table->string('tax_per_item')->default('NO')->after('base_tax'); // YES or NO
            $table->string('sales_tax_type')->nullable()->after('tax_per_item');
            $table->string('sales_tax_address_type')->nullable()->after('sales_tax_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn([
                'tax',
                'base_tax',
                'tax_per_item',
                'sales_tax_type',
                'sales_tax_address_type'
            ]);
        });
    }
};