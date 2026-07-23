<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only ledger: monthly accruals, admin adjustments, and
        // deductions from approved leaves. A balance is just SUM(amount).
        Schema::create('leave_credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);              // vl | sl | wellness | spl
            $table->decimal('amount', 6, 2);         // +1.25 accrued, -3.00 used
            $table->string('period', 7)->nullable(); // 'YYYY-MM' for accruals (idempotency)
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'kind', 'period']);
        });

        Schema::table('employees', function (Blueprint $table) {
            // The month automatic +1.25 accrual begins for this employee.
            $table->date('credits_accrual_start')->nullable()->after('date_assumption');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            // Which balance an approved leave of this type deducts from.
            $table->string('credit_kind', 20)->nullable()->after('detail_group');
            // Unofficial types (e.g. Wellness Leave) print under "Others:" on
            // CS Form No. 6 instead of getting their own 6.A checkbox.
            $table->boolean('is_official')->default(true)->after('credit_kind');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['credit_kind', 'is_official']);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('credits_accrual_start');
        });
        Schema::dropIfExists('leave_credit_entries');
    }
};
