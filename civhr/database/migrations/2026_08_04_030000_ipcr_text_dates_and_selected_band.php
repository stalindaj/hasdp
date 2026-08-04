<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two corrections to the IPCR port.
 *
 * 1. The Form E date cells are NOT calendar dates. The original fills them
 *    from the rating period — "January - June 2026" gives "January" to the
 *    Reviewed/Approved cells and "June 2026" to the Discussed/Assessed/Final
 *    Rating cells — and the ratee may type anything there. Stored as `date`
 *    they were rejected by validation and lost on save, so they become text.
 *
 * 2. Clicking a Performance Standards cell in the matrix marks that band as
 *    the achieved one for its measure (green check) and copies its % down to
 *    Form E. Remember which band was picked so the mark survives a reload.
 */
return new class extends Migration
{
    private const DATE_COLUMNS = [
        'discussed_date',
        'fe_reviewed_date',
        'fe_approved_date',
        'fe_assessed_date',
        'fe_final_rating_date',
    ];

    public function up(): void
    {
        Schema::table('ipcr_forms', function (Blueprint $table) {
            foreach (self::DATE_COLUMNS as $column) {
                $table->string($column, 60)->nullable()->change();
            }
        });

        Schema::table('ipcr_form_rows', function (Blueprint $table) {
            // o | vs | s | u | p — the band picked for this measure.
            $table->string('selected_band', 2)->nullable()->after('poor');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_forms', function (Blueprint $table) {
            foreach (self::DATE_COLUMNS as $column) {
                $table->date($column)->nullable()->change();
            }
        });

        Schema::table('ipcr_form_rows', function (Blueprint $table) {
            $table->dropColumn('selected_band');
        });
    }
};
