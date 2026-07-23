<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The employee number doubles as the login username.
            $table->string('username', 50)->nullable()->unique()->after('name');
        });

        Schema::table('employees', function (Blueprint $table) {
            // Annual Physical Exam tracking (from the HR monitoring sheet).
            $table->date('last_ape_date')->nullable()->after('date_of_birth');
            $table->date('ape_date_started')->nullable()->after('last_ape_date');
            $table->date('ape_date_completed')->nullable()->after('ape_date_started');
        });

        // IPCR submission checkmarks per employee per year (status only — the
        // full IPCR system lives elsewhere; the dashboard tracks compliance).
        Schema::create('ipcr_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->boolean('sem1_done')->default(false);
            $table->boolean('sem2_done')->default(false);
            $table->timestamps();
            $table->unique(['employee_id', 'year']);
        });

        // Learning & Development hours, one row per training attended.
        Schema::create('ld_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('hours', 5, 1);
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ld_entries');
        Schema::dropIfExists('ipcr_records');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['last_ape_date', 'ape_date_started', 'ape_date_completed']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
