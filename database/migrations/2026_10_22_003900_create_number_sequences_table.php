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
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->unique();
            $table->string('label');
            $table->string('prefix', 20)->nullable();
            $table->boolean('include_year')->default(false);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->boolean('reset_yearly')->default(false);
            $table->unsignedSmallInteger('current_year')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
