<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Military rank, e.g. "LTC". Nullable — civilian staff have none.
            $table->string('rank', 30)->nullable()->after('suffix');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            // The printed signature block for each signatory, as its three
            // parts: {rank, name, branch} — rank prints left of the signature
            // line, name centred above it, branch right. Frozen at filing for
            // the same reason as the applicant's details: a later promotion
            // must not rewrite an already-printed form.
            // The applicant signs 6.D and gets the same treatment.
            $table->json('applicant_sig')->nullable()->after('salary');

            $table->json('hr_officer_sig')->nullable()->after('hr_officer_id');
            $table->json('recommender_sig')->nullable()->after('recommender_id');
            $table->json('approver_sig')->nullable()->after('approver_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('rank');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn(['applicant_sig', 'hr_officer_sig', 'recommender_sig', 'approver_sig']);
        });
    }
};
