<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->string('other_leave_type')->nullable();   // 6.A "Others: ____"

            // Snapshot of the printed header (1-5). Frozen at filing so a later
            // promotion or transfer never rewrites an already-printed form.
            $table->string('office_department')->nullable();  // 1
            $table->string('applicant_last_name')->nullable();  // 2
            $table->string('applicant_first_name')->nullable();
            $table->string('applicant_middle_name')->nullable();
            $table->date('date_filing');                      // 3
            $table->string('position')->nullable();           // 4
            $table->string('salary')->nullable();             // 5

            // 6.B details of leave
            $table->string('detail_vacation')->nullable();       // within_philippines|abroad
            $table->string('detail_vacation_location')->nullable();
            $table->string('detail_sick')->nullable();           // in_hospital|out_patient
            $table->string('detail_sick_illness')->nullable();
            $table->string('detail_women_illness')->nullable();
            $table->string('detail_study')->nullable();          // masters|bar_board
            $table->string('detail_study_other')->nullable();
            $table->string('detail_other_purpose')->nullable();  // monetization|terminal

            // 6.C working days + inclusive dates
            $table->decimal('working_days', 5, 2);
            $table->date('date_from');
            $table->date('date_to');

            // 6.D commutation
            $table->string('commutation')->default('not_requested'); // not_requested|requested

            // Routing — chosen by the applicant at filing
            $table->foreignId('hr_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recommender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('pending_certification')->index();

            // 7.A certification of leave credits
            $table->date('cert_as_of')->nullable();
            $table->decimal('vl_earned', 6, 3)->nullable();
            $table->decimal('vl_less', 6, 3)->nullable();
            $table->decimal('vl_balance', 6, 3)->nullable();
            $table->decimal('sl_earned', 6, 3)->nullable();
            $table->decimal('sl_less', 6, 3)->nullable();
            $table->decimal('sl_balance', 6, 3)->nullable();
            $table->timestamp('certified_at')->nullable();

            // 7.B recommendation
            $table->string('recommendation')->nullable();          // approval|disapproval
            $table->text('recommendation_reason')->nullable();
            $table->timestamp('recommended_at')->nullable();

            // 7.C / 7.D action of the approving official
            $table->string('decision')->nullable();                // approved|disapproved
            $table->decimal('days_with_pay', 5, 2)->nullable();
            $table->decimal('days_without_pay', 5, 2)->nullable();
            $table->decimal('days_others', 5, 2)->nullable();
            $table->string('days_others_specify')->nullable();
            $table->text('disapproval_reason')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
