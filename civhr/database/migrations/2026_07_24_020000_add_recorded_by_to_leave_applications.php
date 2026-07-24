<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            // Set when an admin keys in a leave that was taken on paper (or
            // before go-live) rather than filed through the system. Such rows
            // still deduct credits and appear in the logs, but are marked so
            // the audit trail can tell them apart from employee-filed leaves.
            $table->foreignId('recorded_by')->nullable()->after('approver_sig')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
        });
    }
};
