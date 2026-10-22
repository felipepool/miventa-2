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
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->after('id')->unique();
            $table->foreignId('negocio_id')->after('public_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('username', 30)->after('name');
            $table->string('email')->nullable()->change();
            $table->boolean('activo')->default(true)->after('password');

            $table->unique(['negocio_id', 'username']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['negocio_id', 'username']);
            $table->dropConstrainedForeignId('negocio_id');
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'username', 'activo']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
