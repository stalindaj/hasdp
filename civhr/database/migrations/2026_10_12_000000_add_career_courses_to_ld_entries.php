<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ld_entries', function (Blueprint $table) {
            // A local career course or seminar (Table 2 of the HR report) is
            // filed and approved the same way as a training but kept apart
            // from it: its own kind, a batch and who conducted it, and no
            // hours toward the L&D target. Existing rows stay trainings.
            $table->string('kind', 20)->default('training')->after('employee_id');
            $table->string('batch', 50)->nullable()->after('title');
            $table->string('conducted_by', 191)->nullable()->after('batch');
        });
    }

    public function down(): void
    {
        Schema::table('ld_entries', function (Blueprint $table) {
            $table->dropColumn(['kind', 'batch', 'conducted_by']);
        });
    }
};
