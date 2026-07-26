<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Civilian staff print no rank and no service branch on the CS
            // Form 6 signature blocks. Military personnel print "LTC … PAF".
            $table->boolean('is_civilian')->default(true)->after('rank');
        });

        // Backfill: anyone carrying a real military rank is military. Entries
        // like "Civ HR" were typed into the rank box for civilians, so they
        // stay civilian (and that text stops printing).
        DB::table('employees')
            ->whereNotNull('rank')
            ->where('rank', '!=', '')
            ->whereRaw("LOWER(rank) NOT LIKE 'civ%'")
            ->update(['is_civilian' => false]);

        // Signature blocks are frozen into each application at filing time, so
        // leaves already on file still carry "Civ HR / PAF". Strip the rank and
        // branch from those snapshots too, or old forms keep printing them.
        $columns = ['applicant_sig', 'hr_officer_sig', 'recommender_sig', 'approver_sig'];

        DB::table('leave_applications')
            ->select(array_merge(['id'], $columns))
            ->orderBy('id')
            ->chunk(200, function ($apps) use ($columns) {
                foreach ($apps as $app) {
                    $changed = [];

                    foreach ($columns as $col) {
                        $sig = json_decode($app->{$col} ?? '', true);
                        if (! is_array($sig)) {
                            continue;
                        }

                        if (preg_match('/^civ/i', trim((string) ($sig['rank'] ?? '')))) {
                            $sig['rank'] = '';
                            $sig['branch'] = '';
                            $changed[$col] = json_encode($sig);
                        }
                    }

                    if ($changed) {
                        DB::table('leave_applications')->where('id', $app->id)->update($changed);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('is_civilian');
        });
    }
};
