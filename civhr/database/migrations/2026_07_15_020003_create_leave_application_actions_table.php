<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_application_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');                  // filed|certified|recommended|approved|disapproved|cancelled
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_application_actions');
    }
};
