<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('email');
            $table->string('phone', 30)->nullable()->index()->after('username');
            $table->string('photo_path')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->index()->after('photo_path');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->timestamp('last_seen_at')->nullable()->after('last_login_ip');
            $table->unsignedBigInteger('campus_id')->nullable()->index()->after('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['campus_id']);
            $table->dropColumn([
                'username', 'phone', 'photo_path', 'is_active', 'must_change_password',
                'last_login_at', 'last_login_ip', 'last_seen_at', 'campus_id',
            ]);
        });
    }
};
