<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The CSC IPCR groups its outputs under a "Strategic Priority No." line and a
 * "Core Function" line (see the official Form E). Store both so the printed
 * form matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_forms', function (Blueprint $table) {
            $table->string('strategic_priority')->nullable()->after('office_unit');
            $table->string('core_function')->nullable()->after('strategic_priority');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_forms', function (Blueprint $table) {
            $table->dropColumn(['strategic_priority', 'core_function']);
        });
    }
};
