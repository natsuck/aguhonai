<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 125)->nullable()->after('name');
            $table->string('last_name', 125)->nullable()->after('first_name');
            $table->date('birthday')->nullable()->after('last_name');
            $table->string('gender')->nullable()->after('birthday');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'birthday', 'gender']);
        });
    }
};
