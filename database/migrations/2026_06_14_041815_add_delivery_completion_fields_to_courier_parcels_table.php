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
            $table->date('delivered_date')->nullable()->after('parcel_date');
            $table->boolean('instructions_given')->default(false)->after('delivered_date');
            $table->text('instruction_note')->nullable()->after('instructions_given');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courier_parcels', function (Blueprint $table) {
            $table->dropColumn(['delivered_date', 'instructions_given', 'instruction_note']);
        });
    }
};
