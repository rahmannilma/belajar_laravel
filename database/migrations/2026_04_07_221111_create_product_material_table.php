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
        Schema::create('product_material', function (Blueprint $row) {
            $row->id();
            $row->foreignId('product_id')->constrained()->onDelete('cascade');
            $row->foreignId('material_id')->constrained()->onDelete('cascade');
            $row->decimal('quantity', 15, 2)->default(1);
            $row->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_material');
    }
};
