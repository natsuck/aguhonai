<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('program_code', 10)->unique();
            $table->string('program_name');
            $table->unsignedTinyInteger('realistic_weight');
            $table->unsignedTinyInteger('investigative_weight');
            $table->unsignedTinyInteger('artistic_weight');
            $table->unsignedTinyInteger('social_weight');
            $table->unsignedTinyInteger('enterprising_weight');
            $table->unsignedTinyInteger('conventional_weight');
            $table->unsignedTinyInteger('verbal_target');
            $table->unsignedTinyInteger('quantitative_target');
            $table->unsignedTinyInteger('analytical_target');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_profiles');
    }
};
