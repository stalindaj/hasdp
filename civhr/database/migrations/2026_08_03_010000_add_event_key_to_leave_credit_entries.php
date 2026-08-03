<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_credit_entries', function (Blueprint $table) {
            // Idempotency key for one-off automatic postings that are not
            // monthly accruals — currently the year-end forfeiture of unused
            // mandatory leave ('forfeit-fl-2026'). `period` can't carry these:
            // it is 'YYYY-MM' and already unique per accrual row.
            $table->string('event_key', 40)->nullable()->after('period');
            $table->unique(['employee_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::table('leave_credit_entries', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'event_key']);
            $table->dropColumn('event_key');
        });
    }
};
