<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('language', 10)->default('ko');         // 앱 언어 (ko, en, ja ...)
            $table->string('currency', 10)->default('KRW');        // 선호 통화
            $table->string('timezone', 50)->default('Asia/Seoul'); // 타임존
            $table->boolean('notification_enabled')->default(true); // 푸시 알림 여부

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
