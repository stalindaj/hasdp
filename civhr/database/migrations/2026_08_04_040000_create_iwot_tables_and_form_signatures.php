<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IWOT — the Individual Work Output Target sheet the office fills in at the
 * START of a rating period (the official template is the performance-standards
 * matrix: Major Final Output × Timeliness × the three measures × five
 * standards, signed "Prepared by" the employee and "Approved by" the NCOIC).
 * The IPCR at the end of the period rates against these targets, so the two
 * share a shape but stay separate records.
 *
 * Both forms also gain `signature_uploads`: one e-signature image per named
 * block, uploaded onto that form the way CS Form No. 6 already works — the
 * signatories are usually military supervisors with no account here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iwot_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // The three lines printed above the matrix.
            $table->string('position_title')->nullable();
            $table->string('office_unit')->nullable();

            // Not printed — it files the sheet in the list.
            $table->string('rating_period')->nullable();

            $table->string('status')->default('draft'); // draft|submitted|approved|returned

            // Footer signatories, typed (they rarely have accounts here).
            $table->string('prepared_by')->nullable();
            $table->string('prepared_designation')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('approved_designation')->nullable();

            $table->json('signature_uploads')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'rating_period']);
            $table->index('status');
        });

        Schema::create('iwot_form_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iwot_form_id')->constrained('iwot_forms')->cascadeOnDelete();

            $table->text('major_final_output')->nullable();
            $table->text('timeliness')->nullable();
            $table->text('success_indicator')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });

        Schema::create('iwot_form_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iwot_form_id')->constrained('iwot_forms')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('iwot_form_groups')->cascadeOnDelete();

            $table->string('performance_measure'); // Quality | Timeliness | Quantity
            $table->text('performance_targets')->nullable();

            $table->text('outstanding')->nullable();
            $table->text('very_satisfactory')->nullable();
            $table->text('satisfactory')->nullable();
            $table->text('unsatisfactory')->nullable();
            $table->text('poor')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('ipcr_forms', function (Blueprint $table) {
            $table->json('signature_uploads')->nullable()->after('scanned_copy_path');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_forms', function (Blueprint $table) {
            $table->dropColumn('signature_uploads');
        });

        Schema::dropIfExists('iwot_form_rows');
        Schema::dropIfExists('iwot_form_groups');
        Schema::dropIfExists('iwot_forms');
    }
};
