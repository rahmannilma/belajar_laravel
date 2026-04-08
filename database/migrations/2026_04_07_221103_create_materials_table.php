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
        Schema::create('materials', function (Blueprint $row) {
            $row->id();
            $row->string('name');
            $row->string('unit')->default('gr'); // gr, ml, pcs, etc.
            $row->decimal('stock', 15, 2)->default(0);
            $row->decimal('min_stock', 15, 2)->default(0);
            $row->decimal('purchase_price', 15, 2)->default(0);
            $row->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
