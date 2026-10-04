<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drive_connections', fn (Blueprint $table) => $table->string('account_id')->nullable());
    }

    public function down(): void
    {
        Schema::table('drive_connections', fn (Blueprint $table) => $table->dropColumn('account_id'));
    }
};
