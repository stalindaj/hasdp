<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // e.g. 'vacation'
            $table->string('name');                    // printed label, e.g. 'Vacation Leave'
            $table->string('legal_basis')->nullable(); // the fine print beside the label on 6.A
            $table->string('detail_group')->nullable(); // drives 6.B: vacation|sick|women|study|null
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
