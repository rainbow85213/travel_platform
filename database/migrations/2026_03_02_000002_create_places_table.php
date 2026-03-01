<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();

            // 위경도: PostgreSQL 소수점 7자리 (약 1cm 정밀도)
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // restaurant | cafe | hotel | attraction | shopping | transport | etc.
            $table->string('category', 50)->nullable();
            $table->string('thumbnail_url')->nullable();

            // 0.00 ~ 5.00
            $table->decimal('rating', 3, 2)->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['city', 'country']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
