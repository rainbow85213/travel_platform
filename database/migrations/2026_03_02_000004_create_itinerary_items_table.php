<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')
                ->constrained('itineraries')
                ->cascadeOnDelete();   // 일정 삭제 시 아이템도 삭제
            $table->foreignId('place_id')
                ->constrained('places')
                ->restrictOnDelete();  // 장소는 참조 중이면 삭제 불가

            $table->unsignedSmallInteger('day_number');          // 일정 몇 일차 (1, 2, 3 ...)
            $table->unsignedSmallInteger('sort_order')->default(0); // 당일 내 순서

            $table->timestampTz('visited_at')->nullable();       // 예정 방문 일시
            $table->unsignedSmallInteger('duration_minutes')->nullable(); // 예상 체류 시간(분)
            $table->text('notes')->nullable();                   // 메모

            $table->timestampsTz();

            $table->index(['itinerary_id', 'day_number', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_items');
    }
};
