<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\TourCastException;
use App\Services\TourCastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function mockGet(array $return = []): void
    {
        $this->mock(TourCastService::class, function (MockInterface $mock) use ($return) {
            $mock->shouldReceive('proxyScheduleGet')->andReturn($return);
        });
    }

    private function mockPost(array $return = []): void
    {
        $this->mock(TourCastService::class, function (MockInterface $mock) use ($return) {
            $mock->shouldReceive('proxySchedulePost')->andReturn($return);
        });
    }

    private function mockPatch(array $return = []): void
    {
        $this->mock(TourCastService::class, function (MockInterface $mock) use ($return) {
            $mock->shouldReceive('proxySchedulePatch')->andReturn($return);
        });
    }

    private function mockException(string $method): void
    {
        $this->mock(TourCastService::class, function (MockInterface $mock) use ($method) {
            $mock->shouldReceive($method)
                ->andThrow(new TourCastException('TourCast 연결 오류'));
        });
    }

    // =========================================================================
    // 1. 비인증 요청 전체 → 401
    // =========================================================================

    public function test_unauthenticated_map_returns_401(): void
    {
        $this->getJson('/api/schedule/map')->assertStatus(401);
    }

    public function test_unauthenticated_route_returns_401(): void
    {
        $this->getJson('/api/schedule/route')->assertStatus(401);
    }

    public function test_unauthenticated_heatmap_returns_401(): void
    {
        $this->getJson('/api/schedule/heatmap')->assertStatus(401);
    }

    public function test_unauthenticated_list_returns_401(): void
    {
        $this->getJson('/api/schedule/list')->assertStatus(401);
    }

    public function test_unauthenticated_store_returns_401(): void
    {
        $this->postJson('/api/schedule')->assertStatus(401);
    }

    public function test_unauthenticated_update_item_returns_401(): void
    {
        $this->patchJson('/api/schedule/item/abc123')->assertStatus(401);
    }

    public function test_unauthenticated_device_token_returns_401(): void
    {
        $this->postJson('/api/user/device-token')->assertStatus(401);
    }

    // =========================================================================
    // 2. GET /api/schedule/map
    // =========================================================================

    public function test_map_returns_data_with_date_param(): void
    {
        $this->mockGet(['items' => [
            ['id' => 'item1', 'title' => '성산일출봉', 'latitude' => 33.4583, 'longitude' => 126.9422],
        ]]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/schedule/map?date=2026-03-29')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '지도 데이터 조회 성공')
            ->assertJsonPath('data.items.0.title', '성산일출봉');
    }

    // =========================================================================
    // 3. GET /api/schedule/route
    // =========================================================================

    public function test_route_returns_data(): void
    {
        $this->mockGet(['waypoints' => [
            ['lat' => 33.4583, 'lng' => 126.9422],
        ]]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/schedule/route?date=2026-03-29')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '경로 조회 성공')
            ->assertJsonStructure(['data' => ['waypoints']]);
    }

    // =========================================================================
    // 4. GET /api/schedule/heatmap
    // =========================================================================

    public function test_heatmap_returns_data(): void
    {
        $this->mockGet([
            ['lat' => 33.4583, 'lng' => 126.9422, 'weight' => 10],
            ['lat' => 33.4600, 'lng' => 126.9500, 'weight' => 5],
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/schedule/heatmap')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '히트맵 조회 성공');
    }

    // =========================================================================
    // 5. GET /api/schedule/list
    // =========================================================================

    public function test_list_returns_data_with_page_and_limit(): void
    {
        $this->mockGet([
            'schedules' => [['id' => 'plan1', 'title' => '제주도 여행', 'itemCount' => 3]],
            'total'     => 1,
            'hasMore'   => false,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/schedule/list?page=1&limit=10')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '일정 목록 조회 성공')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.hasMore', false);
    }

    // =========================================================================
    // 6. POST /api/schedule — title 없음 → 422
    // =========================================================================

    public function test_store_fails_validation_without_title(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/schedule', ['date' => '2026-04-01'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['title']]);
    }

    // =========================================================================
    // 7. POST /api/schedule — date 없음 → 422 (E2E에서 발견된 필수 필드)
    // =========================================================================

    public function test_store_fails_validation_without_date(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/schedule', ['title' => '제주도 여행'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['date']]);
    }

    // =========================================================================
    // 8. POST /api/schedule — 정상 생성
    // =========================================================================

    public function test_store_creates_schedule_successfully(): void
    {
        $this->mockPost([
            'id'    => 'cmnblvxf2000001lnz2z22uc0',
            'date'  => '2026-04-01',
            'title' => '제주도 3일 여행',
            'items' => [
                ['id' => 'item1', 'title' => '성산일출봉', 'order' => 1, 'status' => 'pending'],
            ],
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/schedule', [
                'title' => '제주도 3일 여행',
                'date'  => '2026-04-01',
                'items' => [
                    [
                        'title'     => '성산일출봉',
                        'latitude'  => 33.4583,
                        'longitude' => 126.9422,
                        'order'     => 1,
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '일정이 생성되었습니다.')
            ->assertJsonPath('data.id', 'cmnblvxf2000001lnz2z22uc0')
            ->assertJsonPath('data.items.0.status', 'pending');
    }

    // =========================================================================
    // 9. PATCH /api/schedule/item/{id} — status 없음 → 422
    // =========================================================================

    public function test_update_item_fails_validation_without_status(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/schedule/item/abc123', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    // =========================================================================
    // 10. PATCH /api/schedule/item/{id} — 정상 업데이트
    // =========================================================================

    public function test_update_item_updates_successfully(): void
    {
        $this->mockPatch(['id' => 'abc123', 'status' => 'visited', 'planId' => 'plan1']);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/schedule/item/abc123', ['status' => 'visited'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '아이템 상태가 업데이트되었습니다.')
            ->assertJsonPath('data.id', 'abc123')
            ->assertJsonPath('data.status', 'visited');
    }

    // =========================================================================
    // 11. POST /api/user/device-token — 잘못된 platform → 422
    // =========================================================================

    public function test_device_token_fails_with_invalid_platform(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/device-token', [
                'device_token'    => 'fcm-token-abc',
                'device_platform' => 'windows',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['device_platform']]);
    }

    // =========================================================================
    // 12. POST /api/user/device-token — 정상 저장 → users 테이블 반영
    // =========================================================================

    public function test_device_token_saves_to_users_table(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/device-token', [
                'device_token'    => 'fcm-token-abc123',
                'device_platform' => 'ios',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '디바이스 토큰이 저장되었습니다.');

        $this->assertDatabaseHas('users', [
            'id'              => $this->user->id,
            'device_token'    => 'fcm-token-abc123',
            'device_platform' => 'ios',
        ]);
    }

    public function test_device_token_updates_existing_token(): void
    {
        $this->user->update(['device_token' => 'old-token', 'device_platform' => 'android']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/device-token', [
                'device_token'    => 'new-token-xyz',
                'device_platform' => 'ios',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id'              => $this->user->id,
            'device_token'    => 'new-token-xyz',
            'device_platform' => 'ios',
        ]);
        $this->assertDatabaseMissing('users', ['device_token' => 'old-token']);
    }

    // =========================================================================
    // 13. TourCastException 발생 시 → success:false, HTTP 500
    // =========================================================================

    public function test_returns_500_on_tourcast_exception_for_map(): void
    {
        $this->mockException('proxyScheduleGet');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/schedule/map')
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    }

    public function test_returns_500_on_tourcast_exception_for_list(): void
    {
        $this->mockException('proxyScheduleGet');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/schedule/list')
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    }

    public function test_returns_500_on_tourcast_exception_for_store(): void
    {
        $this->mockException('proxySchedulePost');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/schedule', [
                'title' => '테스트 일정',
                'date'  => '2026-04-01',
            ])
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    }

    public function test_returns_500_on_tourcast_exception_for_update_item(): void
    {
        $this->mockException('proxySchedulePatch');

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/schedule/item/abc123', ['status' => 'visited'])
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    }

    // =========================================================================
    // 14. userId 자동 주입 검증 — 클라이언트 전달값 무시
    // =========================================================================

    public function test_userId_is_injected_from_auth_ignoring_client_input(): void
    {
        $capturedQuery = null;

        $this->mock(TourCastService::class, function (MockInterface $mock) use (&$capturedQuery) {
            $mock->shouldReceive('proxyScheduleGet')
                ->once()
                ->withArgs(function (string $path, array $query) use (&$capturedQuery): bool {
                    $capturedQuery = $query;

                    return true;
                })
                ->andReturn([]);
        });

        // 클라이언트가 다른 userId 주입 시도
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/schedule/heatmap?userId=user_9999')
            ->assertStatus(200);

        $expected = 'user_' . $this->user->id;

        $this->assertEquals($expected, $capturedQuery['userId'], 'userId must come from authenticated user, not from query string');
        $this->assertNotEquals('user_9999', $capturedQuery['userId']);
    }

    public function test_store_userId_is_injected_from_auth_not_from_body(): void
    {
        $capturedBody = null;

        $this->mock(TourCastService::class, function (MockInterface $mock) use (&$capturedBody) {
            $mock->shouldReceive('proxySchedulePost')
                ->once()
                ->withArgs(function (string $path, array $body) use (&$capturedBody): bool {
                    $capturedBody = $body;

                    return true;
                })
                ->andReturn(['id' => 'plan1', 'title' => '테스트']);
        });

        // 클라이언트가 body에 다른 userId 전달 시도
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/schedule', [
                'title'  => '테스트 일정',
                'date'   => '2026-04-01',
                'userId' => 'user_9999',
            ])
            ->assertStatus(201);

        $expected = 'user_' . $this->user->id;

        $this->assertEquals($expected, $capturedBody['userId'], 'userId in forwarded body must come from authenticated user');
        $this->assertNotEquals('user_9999', $capturedBody['userId']);
    }
}
