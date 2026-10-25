<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table): void {
            $table->foreignId('venta_id')->nullable()->after('user_id')->constrained('ventas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('venta_id');
        });
    }
};
