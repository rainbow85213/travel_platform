<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ItineraryController;
use App\Http\Controllers\Api\ItineraryItemController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (React Native 모바일 앱)
|--------------------------------------------------------------------------
|
| 응답 형식: { "success": bool, "message": string, "data": mixed }
| 인증: Laravel Sanctum (Bearer Token)
|
*/

// =========================================================================
// 인증 불필요
// =========================================================================

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']); // 회원가입
    Route::post('login',    [AuthController::class, 'login']);    // 로그인
});

// =========================================================================
// 인증 필요 (Authorization: Bearer {token})
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {

    // ---------------------------------------------------------------------
    // 챗봇
    // ---------------------------------------------------------------------
    Route::prefix('chat')->group(function () {
        Route::post('',        [ChatController::class, 'chat']);           // 메시지 전송
        Route::get('history',  [ChatController::class, 'history']);        // 기록 조회
        Route::delete('history', [ChatController::class, 'destroyHistory']); // 기록 삭제
    });

    // ---------------------------------------------------------------------
    // 인증
    // ---------------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::get( 'me',         [AuthController::class, 'me']);         // 내 정보
        Route::post('logout',     [AuthController::class, 'logout']);     // 현재 기기 로그아웃
        Route::post('logout-all', [AuthController::class, 'logoutAll']); // 전체 기기 로그아웃
    });

    // ---------------------------------------------------------------------
    // 프로필
    // ---------------------------------------------------------------------
    Route::prefix('profile')->group(function () {
        Route::get('',            [ProfileController::class, 'show']);              // 프로필 조회
        Route::put('',            [ProfileController::class, 'update']);            // 프로필 수정
        Route::put('password',    [ProfileController::class, 'updatePassword']);    // 비밀번호 변경
        Route::get('preferences', [ProfileController::class, 'showPreferences']);   // 선호 설정 조회
        Route::put('preferences', [ProfileController::class, 'updatePreferences']); // 선호 설정 수정
    });

    // ---------------------------------------------------------------------
    // 장소 (읽기 전용)
    // ---------------------------------------------------------------------
    Route::prefix('places')->group(function () {
        Route::get('',        [PlaceController::class, 'index']); // 목록 (검색/필터)
        Route::get('{place}', [PlaceController::class, 'show']);  // 상세
    });

    // ---------------------------------------------------------------------
    // 여행 일정
    // ---------------------------------------------------------------------
    Route::prefix('itineraries')->group(function () {
        Route::get( '',               [ItineraryController::class, 'index']);        // 내 일정 목록
        Route::post('',               [ItineraryController::class, 'store']);        // 일정 생성
        Route::post('save',           [ItineraryController::class, 'saveFromChatbot']); // 챗봇 일정 저장
        Route::get( '{itinerary}',    [ItineraryController::class, 'show']);    // 일정 상세 (items.place 포함)
        Route::put( '{itinerary}',    [ItineraryController::class, 'update']); // 일정 수정
        Route::delete('{itinerary}',  [ItineraryController::class, 'destroy']); // 일정 삭제 (SoftDelete)

        // 일정 아이템 (중첩 리소스)
        Route::prefix('{itinerary}/items')->group(function () {
            Route::get( '',       [ItineraryItemController::class, 'index']);   // 아이템 목록
            Route::post('',       [ItineraryItemController::class, 'store']);   // 아이템 추가
            Route::put( '{item}', [ItineraryItemController::class, 'update']); // 아이템 수정
            Route::delete('{item}', [ItineraryItemController::class, 'destroy']); // 아이템 삭제
        });
    });
});
