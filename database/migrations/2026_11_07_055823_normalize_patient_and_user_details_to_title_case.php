<?php

use App\Models\Patient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('patients')) {
            DB::table('patients')
                ->select(['id', 'name', 'city', 'address'])
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $updates = [];

                        $name = Patient::formatTitleCase($row->name);
                        if ($name !== $row->name) {
                            $updates['name'] = $name;
                        }

                        if ($row->city !== null) {
                            $city = Patient::formatTitleCase($row->city);
                            if ($city !== $row->city) {
                                $updates['city'] = $city;
                            }
                        }

                        if ($row->address !== null) {
                            $address = Patient::formatTitleCase($row->address);
                            if ($address !== $row->address) {
                                $updates['address'] = $address;
                            }
                        }

                        if ($updates !== []) {
                            DB::table('patients')->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->select(['id', 'name'])
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $name = Patient::formatTitleCase($row->name);
                        if ($name !== $row->name) {
                            DB::table('users')->where('id', $row->id)->update(['name' => $name]);
                        }
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data normalization
    }
};
