<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drive_connections', function (Blueprint $table) {
            $table->string('role')->default('active')->index();
            $table->string('account_email')->nullable();
        });
        Schema::table('case_files', fn (Blueprint $table) => $table->string('migrated_provider_id')->nullable());
    }

    public function down(): void
    {
        Schema::table('case_files', fn (Blueprint $table) => $table->dropColumn('migrated_provider_id'));
        Schema::table('drive_connections', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'account_email']);
        });
    }
};
