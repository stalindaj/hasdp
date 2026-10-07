<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ld_entries', function (Blueprint $table) {
            // The L&D track a training belongs to (foundational / technical /
            // supervisory / managerial), and the end of a multi-day training —
            // `date` stays the first day, so old single-day rows still read
            // correctly with date_to left null.
            $table->string('competency', 20)->nullable()->after('employee_id');
            $table->date('date_to')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('ld_entries', function (Blueprint $table) {
            $table->dropColumn(['competency', 'date_to']);
        });
    }
};
