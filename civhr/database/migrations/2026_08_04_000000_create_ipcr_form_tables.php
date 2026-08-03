<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The full IPCR form, ported natively from the standalone PHP app.
 *
 * Shape (unchanged from the original so the logic carries over):
 *   ipcr_forms          one form per ratee per rating period (+ final evaluation)
 *     └ ipcr_form_groups  one row per Major Final Output / KRA (accomplishment,
 *                         Quality/Timeliness/Quantity ratings, average, remarks)
 *         └ ipcr_form_rows  the 3 measures per group, each holding the target
 *                           descriptors for the 5 rating levels
 *
 * This is separate from `ipcr_records` (the dashboard compliance checkmarks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_forms', function (Blueprint $table) {
            $table->id();
            // The ratee — a user account (employee-role) in the shared table.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('rating_period');
            $table->string('position_title')->nullable();
            $table->string('office_unit')->nullable();
            $table->string('status')->default('draft'); // draft|submitted|reviewed|approved|rejected

            // Part IV — signatories (free text names, mirroring the CSC form).
            $table->string('prepared_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('discussed_with')->nullable();
            $table->date('discussed_date')->nullable();

            // Final evaluation (Part E) — review / approval / assessment trail.
            $table->string('fe_reviewed_by')->nullable();
            $table->date('fe_reviewed_date')->nullable();
            $table->string('fe_approved_by')->nullable();
            $table->date('fe_approved_date')->nullable();
            $table->text('fe_review_remarks')->nullable();

            $table->string('fe_assessed_by')->nullable();
            $table->date('fe_assessed_date')->nullable();
            $table->string('fe_final_rating_by')->nullable();
            $table->date('fe_final_rating_date')->nullable();

            $table->text('fe_comments')->nullable();
            $table->text('fe_intervening_activity')->nullable();
            $table->text('fe_intervening_activities')->nullable(); // JSON list

            // Computed scores (server-authoritative on save).
            $table->decimal('fe_average_point_score', 4, 2)->nullable();
            $table->decimal('fe_overall_point_score', 4, 2)->nullable();
            $table->decimal('fe_overall_numerical_rating', 4, 2)->nullable();
            $table->string('fe_overall_adjectival_rating')->nullable();

            // Convenience mirror of the overall rating for list/sort.
            $table->decimal('overall_rating', 4, 2)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'rating_period']);
            $table->index('status');
        });

        Schema::create('ipcr_form_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_form_id')->constrained('ipcr_forms')->cascadeOnDelete();

            $table->text('major_final_output')->nullable(); // MFO / KRA
            $table->text('timeliness')->nullable();          // kept from the original schema
            $table->text('success_indicator')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->text('actual_accomplishment')->nullable();

            $table->decimal('quality_pct', 5, 2)->nullable();
            $table->decimal('timeliness_pct', 5, 2)->nullable();
            $table->decimal('quantity_pct', 5, 2)->nullable();

            $table->decimal('quality_rating', 4, 2)->nullable();
            $table->decimal('timeliness_rating', 4, 2)->nullable();
            $table->decimal('quantity_rating', 4, 2)->nullable();
            $table->decimal('average_rating', 4, 2)->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();
        });

        Schema::create('ipcr_form_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_form_id')->constrained('ipcr_forms')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('ipcr_form_groups')->cascadeOnDelete();

            $table->string('performance_measure'); // Quality | Timeliness | Quantity
            $table->text('performance_targets')->nullable();

            // Target descriptors per rating level.
            $table->text('outstanding')->nullable();
            $table->text('very_satisfactory')->nullable();
            $table->text('satisfactory')->nullable();
            $table->text('unsatisfactory')->nullable();
            $table->text('poor')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_form_rows');
        Schema::dropIfExists('ipcr_form_groups');
        Schema::dropIfExists('ipcr_forms');
    }
};
