<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web customers have no WhatsApp id, so the 10-digit phone becomes the key that
 * links the same person across channels.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('wa_id')->nullable()->change();
            $table->string('phone', 10)->nullable()->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->string('phone')->nullable()->change();
            $table->string('wa_id')->nullable(false)->change();
        });
    }
};
