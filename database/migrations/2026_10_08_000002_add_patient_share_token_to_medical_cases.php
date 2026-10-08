<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_cases', function (Blueprint $table) {
            $table->string('patient_share_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('medical_cases', function (Blueprint $table) {
            $table->dropUnique(['patient_share_token']);
            $table->dropColumn('patient_share_token');
        });
    }
};
