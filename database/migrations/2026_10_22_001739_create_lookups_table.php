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
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
