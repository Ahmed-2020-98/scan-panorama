<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_code', 30)->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_type_id')->constrained()->restrictOnDelete();
            $table->date('exam_date')->index();
            $table->text('notes_internal')->nullable();
            $table->text('notes_for_doctor')->nullable();

            $table->string('share_token', 64)->unique();
            $table->timestamp('share_expires_at')->nullable();
            $table->timestamp('share_revoked_at')->nullable();
            $table->timestamp('shared_at')->nullable();

            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['doctor_id', 'exam_date']);
        });

        Schema::create('case_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_case_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['medical_case_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_files');
        Schema::dropIfExists('medical_cases');
    }
};
