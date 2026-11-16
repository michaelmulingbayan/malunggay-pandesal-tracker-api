<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each row is a price change; the newest row is the current price. Past sales keep
     * their own price_per_piece, so changing the price never alters earlier totals.
     */
    public function up(): void
    {
        Schema::create('pandesal_prices', function (Blueprint $table) {
            $table->id();
            $table->decimal('price_per_piece', 10, 2);
            $table->foreignId('set_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pandesal_prices');
    }
};
