<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('permission_overrides')->nullable();
        });
        DB::table('users')->where('role', 'admin')->update(['role' => 'manager']);
        Schema::create('branch_patient', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unique(['branch_id', 'patient_id']);
        });
        DB::table('medical_cases')->select('branch_id', 'patient_id')->distinct()->orderBy('branch_id')->get()
            ->each(fn ($row) => DB::table('branch_patient')->insertOrIgnore((array) $row));
        Schema::table('exam_types', function (Blueprint $table) {
            $table->unsignedBigInteger('base_price_minor')->nullable();
            $table->text('description')->nullable();
        });
        Schema::create('branch_exam_type', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('base_price_minor')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique(['branch_id', 'exam_type_id']);
        });
        Schema::table('medical_cases', function (Blueprint $table) {
            $table->unsignedBigInteger('doctor_id')->nullable()->change();
            $table->unsignedBigInteger('exam_type_id')->nullable()->change();
            $table->foreignId('technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('workflow_status', 20)->default('new')->index();
            $table->text('medical_notes')->nullable();
            $table->text('technical_notes')->nullable();
            $table->unsignedBigInteger('base_price_minor')->nullable();
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('final_price_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('discount_reason')->nullable();
            $table->foreignId('discount_by')->nullable()->constrained('users');
            $table->timestamp('discount_at')->nullable();
            $table->uuid('deletion_batch_id')->nullable();
        });
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('birth_year_only')->default(false);
            $table->boolean('identity_incomplete')->default(false);
            $table->uuid('deletion_batch_id')->nullable();
        });
        Schema::table('case_files', function (Blueprint $table) {
            $table->string('disk')->default('local');
            $table->string('provider_id')->nullable();
            $table->string('storage_status', 20)->default('ready')->index();
            $table->text('storage_error')->nullable();
            $table->string('staging_path')->nullable();
            $table->boolean('is_shared')->default(true);
            $table->uuid('deletion_batch_id')->nullable();
            $table->softDeletes();
        });
        DB::table('case_files')->update(['disk' => config('radiology.disk', 'local')]);
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_case_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('method', 20);
            $table->foreignId('collected_by')->constrained('users');
            $table->timestamp('received_at')->index();
            $table->uuid('request_id')->unique();
            $table->foreignId('adjustment_of_id')->nullable()->constrained('payments');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
        Schema::create('doctor_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('responsible_user_id')->constrained('users');
            $table->date('visited_at')->nullable()->index();
            $table->date('next_visit_at')->nullable()->index();
            $table->text('comment')->nullable();
            $table->text('agreement')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('drive_connections', function (Blueprint $table) {
            $table->id();
            $table->text('refresh_token');
            $table->string('root_folder_id')->nullable();
            $table->string('shared_drive_id')->nullable();
            $table->timestamps();
        });
        Schema::create('drive_folders', function (Blueprint $table) {
            $table->id();
            $table->string('logical_key')->unique();
            $table->string('provider_id');
            $table->timestamps();
        });
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('medical_case_id')->constrained();
            $table->string('upload_id', 64);
            $table->string('type', 20);
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('total_chunks');
            $table->foreignId('case_file_id')->nullable()->constrained('case_files');
            $table->string('status')->default('receiving');
            $table->timestamps();
            $table->unique(['user_id', 'upload_id']);
        });
    }

    public function down(): void
    {
        // Downgrading this release loses role, finance, and recovery history.
        // Restore the pre-release database backup instead of a destructive rollback.
        throw new RuntimeException('Restore a database backup to downgrade this data migration.');
    }
};
