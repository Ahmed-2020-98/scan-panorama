<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            // Private, revocable link that lists all of the doctor's cases without logging in.
            $table->string('link_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropUnique(['link_token']);
            $table->dropColumn('link_token');
        });
    }
};
