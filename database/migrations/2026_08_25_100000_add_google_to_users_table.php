<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The avatar reuses the existing avatar_url column; whichever
            // provider signed in last owns it.
            $table->string('google_id')->nullable()->unique()->after('avatar_url');
            $table->string('google_nickname')->nullable()->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_google_id_unique');

            $table->dropColumn([
                'google_id',
                'google_nickname',
            ]);
        });
    }
};
