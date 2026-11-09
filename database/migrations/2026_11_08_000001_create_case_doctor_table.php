<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Additional doctors who may see a case besides its main (referring) doctor. */
    public function up(): void
    {
        Schema::create('case_doctor', function (Blueprint $table) {
            $table->foreignId('medical_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['medical_case_id', 'doctor_id']);
            $table->index('doctor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_doctor');
    }
};
