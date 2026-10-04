<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('title', 30)->default('د.');
            $table->string('whatsapp', 30)->nullable();
            $table->string('specialty')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_doctor', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->primary(['branch_id', 'doctor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_doctor');
        Schema::dropIfExists('doctors');
    }
};
