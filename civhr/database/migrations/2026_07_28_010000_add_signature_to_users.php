<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'signature_path')) {
            Schema::table('users', function (Blueprint $table) {
                // A scan of the person's wet signature, printed over their
                // name on CS Form 6. Stored privately — served only through
                // the guarded route, never as a public file.
                $table->string('signature_path')->nullable()->after('password');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};
