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
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable()->unique();
            $table->string('channel');
            $table->foreignId('location_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('printer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('pdf_path')->nullable();
            $table->unsignedInteger('total_pages')->nullable();
            $table->string('page_range')->nullable();
            $table->unsignedSmallInteger('copies')->default(1);
            $table->boolean('color')->default(false);
            $table->string('duplex')->default('simplex');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('status')->default('received');
            $table->text('error_message')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['printer_id', 'status', 'queued_at']);
            $table->index(['status', 'ready_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
