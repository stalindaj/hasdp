<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the IPCR up to parity with Leave: pickable signatories (the CSC IPCR
 * template names an NCOIC reviewer and an SC/CO approver), an admin approval
 * step, and a scanned wet-signed copy uploaded after approval.
 *
 * Signatory names are frozen into the form as JSON snapshots at submit time
 * (mirroring the leave *_sig blocks), while the *_id links stay so an approver
 * can be routed to and e-signatures can resolve live later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_forms', function (Blueprint $table) {
            // Chosen signatories (template: NCOIC reviews/assesses, SC/CO
            // approves/finalises; the ratee signs "Discussed with").
            $table->foreignId('reviewer_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->after('reviewer_id')
                ->constrained('users')->nullOnDelete();

            // Frozen name/designation snapshots for the printed form.
            $table->json('ratee_sig')->nullable()->after('overall_rating');
            $table->json('reviewer_sig')->nullable()->after('ratee_sig');
            $table->json('approver_sig')->nullable()->after('reviewer_sig');

            // Approval trail.
            $table->timestamp('submitted_at')->nullable()->after('approver_sig');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->foreignId('approved_by_id')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();

            // Scanned, wet-signed copy (uploaded once approved).
            $table->string('scanned_copy_path')->nullable()->after('approved_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropConstrainedForeignId('approver_id');
            $table->dropConstrainedForeignId('approved_by_id');
            $table->dropColumn([
                'ratee_sig', 'reviewer_sig', 'approver_sig',
                'submitted_at', 'approved_at', 'scanned_copy_path',
            ]);
        });
    }
};
