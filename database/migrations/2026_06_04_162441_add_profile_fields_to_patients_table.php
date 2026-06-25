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
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'phone')) {
                $table->string('phone', 30)->nullable()->after('email');
            }

            if (! Schema::hasColumn('patients', 'country_code')) {
                $table->char('country_code', 2)->default('IN')->after('phone');
            }

            if (! Schema::hasColumn('patients', 'country_calling_code')) {
                $table->string('country_calling_code', 6)->default('91')->after('country_code');
            }

            if (! Schema::hasColumn('patients', 'patient_number')) {
                $table->string('patient_number', 40)->nullable()->unique()->after('country_calling_code');
            }

            if (! Schema::hasColumn('patients', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('patient_number');
            }

            if (! Schema::hasColumn('patients', 'gender')) {
                $table->string('gender', 20)->nullable()->after('date_of_birth');
            }

            if (! Schema::hasColumn('patients', 'address')) {
                $table->text('address')->nullable()->after('gender');
            }

            if (! Schema::hasColumn('patients', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'patient_number')) {
                $table->dropUnique(['patient_number']);
            }

            $columns = collect([
                'phone',
                'country_code',
                'country_calling_code',
                'patient_number',
                'date_of_birth',
                'gender',
                'address',
                'city',
            ])->filter(fn (string $column): bool => Schema::hasColumn('patients', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
