<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leave_applications', 'signature_uploads')) {
            Schema::table('leave_applications', function (Blueprint $table) {
                // A per-form signature image for any of the four blocks
                // (applicant, 7.A certifier, 7.B recommender, 7.C/7.D approver),
                // keyed by slot => stored path. Uploaded straight from the
                // printed form; it prints over the name, ahead of the
                // signatory's account e-signature. Stored privately and served
                // only through the guarded route, never as a public file.
                $table->json('signature_uploads')->nullable()->after('approver_sig');
            });
        }
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('signature_uploads');
        });
    }
};
