<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Non-working days (regular holidays + special non-working days, per
        // the yearly proclamations). 6.C skips these when counting the days
        // between the inclusive dates.
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('leave_types', function (Blueprint $table) {
            // How 6.C counts the inclusive dates: 'working' skips weekends and
            // holidays; 'calendar' counts every day (e.g. maternity leave is
            // 105 calendar days under R.A. 11210).
            $table->string('day_basis', 10)->default('working')->after('credit_kind');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            // 7.B is no longer picked from the user list — the admin types the
            // recommending officer (name/rank/office) straight into
            // recommender_sig, so the FK has nothing left to point at.
            $table->dropConstrainedForeignId('recommender_id');
        });

        // The role only existed to feed the old 7.B picker.
        DB::table('roles')->where('name', 'recommender')->delete();
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->foreignId('recommender_id')->nullable()
                ->after('hr_officer_sig')->constrained('users')->nullOnDelete();
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('day_basis');
        });

        Schema::dropIfExists('holidays');
    }
};
