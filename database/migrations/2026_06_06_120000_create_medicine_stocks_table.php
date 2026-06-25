<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('size_id')->constrained('sizes')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('quantity_reduction')->default(1);
            $table->timestamps();

            $table->unique(['medicine_id', 'category_id', 'size_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_stocks');
    }
};
