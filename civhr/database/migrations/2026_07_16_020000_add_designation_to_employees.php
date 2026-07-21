<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // The functional title a signatory signs under, e.g.
            // "Director for Personnel", "Wing Civilian Supervisor", "MPMBR".
            // Distinct from `position` (the plantilla title) — both can print.
            $table->string('designation')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('designation');
        });
    }
};
