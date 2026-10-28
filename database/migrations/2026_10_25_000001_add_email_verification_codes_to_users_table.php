<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_code_hash')->nullable();
            $table->timestamp('email_verification_code_expires_at')->nullable();
            $table->unsignedTinyInteger('email_verification_code_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_verification_code_hash',
                'email_verification_code_expires_at',
                'email_verification_code_attempts',
            ]);
        });
    }
};
