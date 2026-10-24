<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff can work in several branches; users.branch_id stays as the last-used one.
        Schema::create('branch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'branch_id']);
        });

        DB::table('branch_user')->insertUsing(['user_id', 'branch_id'],
            DB::table('users')->select('id', 'branch_id')->whereNotNull('branch_id')->whereNotIn('role', ['manager', 'doctor']));

        // Days and hours a staff account may be used; null means always.
        Schema::table('users', function (Blueprint $table) {
            $table->json('work_days')->nullable();
            $table->time('work_from')->nullable();
            $table->time('work_until')->nullable();
            $table->dateTime('open_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['work_days', 'work_from', 'work_until', 'open_until']);
        });
        Schema::dropIfExists('branch_user');
    }
};
