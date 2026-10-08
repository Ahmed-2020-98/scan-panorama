<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->softDeletes();
            $table->uuid('deletion_batch_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['deletion_batch_id']);
            $table->dropColumn(['deleted_at', 'deletion_batch_id']);
        });
    }
};
