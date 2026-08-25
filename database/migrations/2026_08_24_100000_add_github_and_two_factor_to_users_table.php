<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // GitHub-only accounts never set a local password.
            $table->string('password')->nullable()->change();

            $table->string('github_id')->nullable()->unique()->after('password');
            $table->string('github_nickname')->nullable()->after('github_id');
            $table->string('avatar_url')->nullable()->after('github_nickname');

            $table->text('two_factor_secret')->nullable()->after('avatar_url');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            // Highest TOTP timestamp already accepted, so a code can never be replayed.
            $table->unsignedBigInteger('two_factor_last_timestamp')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'github_id',
                'github_nickname',
                'avatar_url',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_last_timestamp',
            ]);

            $table->string('password')->nullable(false)->change();
        });
    }
};
