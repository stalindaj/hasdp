<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('item_no')->nullable();              // Item Nr
            $table->string('psipop_placement')->nullable();     // PSIPOP Placement
            $table->string('emp_no')->nullable()->unique();     // Emp Nr
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('suffix')->nullable();
            $table->date('date_orig_appt')->nullable();         // Date of Orig Appt
            $table->date('date_assumption')->nullable();        // Date of Assumption to Duty
            $table->date('date_of_birth')->nullable();
            $table->date('date_of_promotion')->nullable();
            $table->string('sex', 10)->nullable();
            $table->unsignedTinyInteger('salary_grade')->nullable();   // SG
            $table->unsignedTinyInteger('step_increment')->nullable(); // SI
            $table->string('level')->nullable();
            $table->string('position')->nullable();
            $table->string('position_title_2005')->nullable();  // Position Title (2005)
            $table->string('position_id')->nullable();
            $table->string('tin_no')->nullable();
            $table->string('philhealth_no')->nullable();
            $table->string('pagibig_mid')->nullable();
            $table->string('email')->nullable()->unique();      // used to match on sign-up
            $table->string('contact_no')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};