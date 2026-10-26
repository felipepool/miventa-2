<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->timestamp('anulada_at')->nullable()->after('pagada_at');
            $table->foreignId('anulada_por')->nullable()->after('anulada_at')->constrained('users')->restrictOnDelete();
            $table->string('motivo_anulacion', 150)->nullable()->after('anulada_por');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('anulada_por');
            $table->dropColumn(['anulada_at', 'motivo_anulacion']);
        });
    }
};
