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
        Schema::table('courier_parcels', function (Blueprint $table) {
            $table->text('complaints')->nullable()->after('address');
            $table->integer('days_prescription')->nullable()->after('complaints');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courier_parcels', function (Blueprint $table) {
            $table->dropColumn(['complaints', 'days_prescription']);
        });
    }
};

