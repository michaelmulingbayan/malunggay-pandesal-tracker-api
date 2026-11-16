<?php

use App\StockReturnStatus;
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
        Schema::create('stock_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained('stock_distributions')->restrictOnDelete();
            $table->unsignedInteger('quantity_returned');
            $table->dateTime('return_date');
            $table->enum('status', array_column(StockReturnStatus::cases(), 'value'))->default(StockReturnStatus::Pending->value);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_returns');
    }
};
