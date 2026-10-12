<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A career course taken outside the PAF goes on Table 3 instead of
        // Table 2 of the accomplishment report.
        Schema::table('ld_entries', function (Blueprint $table) {
            $table->boolean('outside_paf')->default(false)->after('conducted_by');
        });

        // Hand edits on the L&D accomplishment report. Each table is computed
        // live from the approved filings; a row here replaces one cell of it
        // for one period, and deleting the row puts the live value back.
        Schema::create('report_cells', function (Blueprint $table) {
            $table->id();
            $table->string('report', 30);     // t1 … t5, monitoring
            $table->string('period', 10);     // "2026" or "2026-Q3"
            $table->string('row_key', 64);    // stable key of the computed row
            $table->string('col', 40);
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['report', 'period', 'row_key', 'col']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cells');
        Schema::table('ld_entries', function (Blueprint $table) {
            $table->dropColumn('outside_paf');
        });
    }
};
