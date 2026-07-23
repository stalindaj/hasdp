<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ld_entries', function (Blueprint $table) {
            // L&D is now filed by the employee with photo proof and approved
            // by an admin — only approved hours count toward the target.
            // Rows an admin logs directly are approved immediately; the
            // default keeps any pre-existing rows counting as before.
            $table->string('status', 10)->default('approved')->after('date');
            $table->string('certificate_path')->nullable()->after('status');
            $table->string('photo_path')->nullable()->after('certificate_path');
            $table->string('remarks')->nullable()->after('photo_path');
            $table->foreignId('submitted_by')->nullable()->after('remarks')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->after('submitted_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            // The wet-signed CS Form No. 6, uploaded by the employee after
            // approval so the office keeps a digital copy on file.
            $table->string('signed_form_path')->nullable()->after('disapproval_reason');
            $table->timestamp('signed_form_uploaded_at')->nullable()->after('signed_form_path');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn(['signed_form_path', 'signed_form_uploaded_at']);
        });

        Schema::table('ld_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['status', 'certificate_path', 'photo_path', 'remarks', 'decided_at']);
        });
    }
};
