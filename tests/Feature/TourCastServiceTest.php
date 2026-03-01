<?php

namespace Tests\Feature;

use App\Services\TourCastException;
use App\Services\TourCastService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class TourCastServiceTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * 재시도 없는 단순 목 클라이언트를 가진 서비스 생성.
     * 성공/실패 응답 단위 검증에 사용.
     *
     * @param  Response[]|ConnectException[]  $responses
     */
    private function makeService(array $responses): TourCastService
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new TourCastService($client);
    }

    /**
     * 재시도 미들웨어(딜레이 0ms)를 포함한 서비스 생성.
     * Retry 로직 검증에 사용.
     *
     * @param  Response[]|ConnectException[]  $responses
     */
    private function makeServiceWithRetry(array $responses, int $maxRetries = 3): TourCastService
    {
        $mock    = new MockHandler($responses);
        $stack   = HandlerStack::create($mock);

        $stack->push(Middleware::retry(
            function (int $retries, $request, $response, $exception) use ($maxRetries): bool {
                if ($retries >= $maxRetries) {
                    return false;
                }
                if ($exception instanceof ConnectException) {
                    return true;
                }
                if ($response && in_array($response->getStatusCode(), [429, 500, 502, 503, 504])) {
                    return true;
                }
                return false;
            },
            fn (): int => 0  // 테스트에서는 딜레이 없음
        ));

        $client = new Client(['handler' => $stack]);

        return new TourCastService($client);
    }

    /**
     * 요청 이력을 기록하는 클라이언트를 가진 서비스 생성.
     * 쿼리 파라미터 검증에 사용.
     *
     * @param  array<int, array<string, mixed>>  &$history
     */
    private function makeServiceWithHistory(array $responses, array &$history): TourCastService
    {
        $mock    = new MockHandler($responses);
        $stack   = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new Client(['handler' => $stack]);

        return new TourCastService($client);
    }

    // =========================================================================
    // getTours
    // =========================================================================

    public function test_get_tours_returns_parsed_array(): void
    {
        $body    = ['data' => [['id' => 1, 'name' => 'Seoul City Tour'], ['id' => 2, 'name' => 'Jeju Tour']]];
        $service = $this->makeService([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($body)),
        ]);

        $result = $service->getTours();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
        $this->assertEquals('Seoul City Tour', $result['data'][0]['name']);
    }

    public function test_get_tours_passes_query_params_to_request(): void
    {
        $history = [];
        $service = $this->makeServiceWithHistory(
            [new Response(200, [], json_encode(['data' => []]))],
            $history
        );

        $service->getTours(['page' => 2, 'per_page' => 10]);

        $query = $history[0]['request']->getUri()->getQuery();
        $this->assertStringContainsString('page=2', $query);
        $this->assertStringContainsString('per_page=10', $query);
    }

    public function test_get_tours_with_no_params_sends_no_query_string(): void
    {
        $history = [];
        $service = $this->makeServiceWithHistory(
            [new Response(200, [], json_encode([]))],
            $history
        );

        $service->getTours();

        $this->assertEmpty($history[0]['request']->getUri()->getQuery());
    }

    // =========================================================================
    // getTour
    // =========================================================================

    public function test_get_tour_returns_single_tour(): void
    {
        $body    = ['id' => 42, 'name' => 'Jeju Island Tour', 'price' => 50000];
        $service = $this->makeService([
            new Response(200, [], json_encode($body)),
        ]);

        $result = $service->getTour(42);

        $this->assertEquals(42, $result['id']);
        $this->assertEquals('Jeju Island Tour', $result['name']);
        $this->assertEquals(50000, $result['price']);
    }

    public function test_get_tour_includes_id_in_url(): void
    {
        $history = [];
        $service = $this->makeServiceWithHistory(
            [new Response(200, [], json_encode(['id' => 7]))],
            $history
        );

        $service->getTour(7);

        $this->assertStringContainsString('/tours/7', $history[0]['request']->getUri()->getPath());
    }

    // =========================================================================
    // searchTours
    // =========================================================================

    public function test_search_tours_returns_matching_results(): void
    {
        $body    = ['data' => [['id' => 3, 'name' => '부산 투어']]];
        $service = $this->makeService([
            new Response(200, [], json_encode($body)),
        ]);

        $result = $service->searchTours('부산');

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('부산 투어', $result['data'][0]['name']);
    }

    public function test_search_tours_includes_keyword_in_query(): void
    {
        $history = [];
        $service = $this->makeServiceWithHistory(
            [new Response(200, [], json_encode(['data' => []]))],
            $history
        );

        $service->searchTours('경복궁');

        $query = $history[0]['request']->getUri()->getQuery();
        $this->assertStringContainsString('q=', $query);
    }

    public function test_search_tours_merges_extra_params(): void
    {
        $history = [];
        $service = $this->makeServiceWithHistory(
            [new Response(200, [], json_encode(['data' => []]))],
            $history
        );

        $service->searchTours('서울', ['page' => 1, 'category' => 'culture']);

        $query = $history[0]['request']->getUri()->getQuery();
        $this->assertStringContainsString('page=1', $query);
        $this->assertStringContainsString('category=culture', $query);
    }

    // =========================================================================
    // Error Handling
    // =========================================================================

    public function test_throws_tourcast_exception_on_404(): void
    {
        $service = $this->makeService([
            new Response(404, [], json_encode(['message' => 'Tour not found'])),
        ]);

        $this->expectException(TourCastException::class);
        $this->expectExceptionCode(404);

        $service->getTour(99999);
    }

    public function test_throws_tourcast_exception_on_401_unauthorized(): void
    {
        $service = $this->makeService([
            new Response(401, [], json_encode(['message' => 'Unauthorized'])),
        ]);

        $this->expectException(TourCastException::class);
        $this->expectExceptionCode(401);

        $service->getTours();
    }

    public function test_throws_tourcast_exception_on_connect_error(): void
    {
        $service = $this->makeService([
            new ConnectException('Connection refused', new Request('GET', '/tours')),
        ]);

        $this->expectException(TourCastException::class);
        $this->expectExceptionCode(0);

        $service->getTours();
    }

    public function test_exception_message_contains_status_code(): void
    {
        $service = $this->makeService([
            new Response(503, [], 'Service Unavailable'),
        ]);

        try {
            $service->getTours();
            $this->fail('TourCastException이 발생해야 합니다.');
        } catch (TourCastException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
        }
    }

    public function test_connect_exception_message_contains_reason(): void
    {
        $service = $this->makeService([
            new ConnectException('Connection timed out', new Request('GET', '/tours')),
        ]);

        try {
            $service->getTours();
            $this->fail('TourCastException이 발생해야 합니다.');
        } catch (TourCastException $e) {
            $this->assertStringContainsString('Connection timed out', $e->getMessage());
        }
    }

    // =========================================================================
    // Retry Logic
    // =========================================================================

    public function test_retries_on_503_and_eventually_succeeds(): void
    {
        $body    = ['data' => [['id' => 1, 'name' => 'Seoul Tour']]];
        $service = $this->makeServiceWithRetry([
            new Response(503, [], 'Service Unavailable'),
            new Response(503, [], 'Service Unavailable'),
            new Response(200, [], json_encode($body)),
        ]);

        $result = $service->getTours();

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('Seoul Tour', $result['data'][0]['name']);
    }

    public function test_retries_on_500_and_eventually_succeeds(): void
    {
        $body    = ['id' => 1, 'name' => 'Busan Tour'];
        $service = $this->makeServiceWithRetry([
            new Response(500, [], 'Internal Server Error'),
            new Response(200, [], json_encode($body)),
        ]);

        $result = $service->getTour(1);

        $this->assertEquals('Busan Tour', $result['name']);
    }

    public function test_retries_on_429_rate_limit_and_eventually_succeeds(): void
    {
        $body    = ['data' => []];
        $service = $this->makeServiceWithRetry([
            new Response(429, [], 'Too Many Requests'),
            new Response(200, [], json_encode($body)),
        ]);

        $result = $service->getTours();

        $this->assertIsArray($result);
    }

    public function test_retries_on_connect_exception_and_eventually_succeeds(): void
    {
        $body    = ['data' => [['id' => 5]]];
        $service = $this->makeServiceWithRetry([
            new ConnectException('Connection refused', new Request('GET', '/tours')),
            new ConnectException('Connection refused', new Request('GET', '/tours')),
            new Response(200, [], json_encode($body)),
        ]);

        $result = $service->getTours();

        $this->assertCount(1, $result['data']);
    }

    public function test_throws_after_max_retries_exceeded(): void
    {
        // 4개 응답 = 초기 1회 + 재시도 3회 → 모두 실패
        $service = $this->makeServiceWithRetry([
            new Response(503, [], 'Service Unavailable'),
            new Response(503, [], 'Service Unavailable'),
            new Response(503, [], 'Service Unavailable'),
            new Response(503, [], 'Service Unavailable'),
        ], maxRetries: 3);

        $this->expectException(TourCastException::class);

        $service->getTours();
    }

    public function test_does_not_retry_on_400_bad_request(): void
    {
        // 재시도 대상이 아닌 4xx는 즉시 실패
        $service = $this->makeServiceWithRetry([
            new Response(400, [], json_encode(['message' => 'Bad Request'])),
        ]);

        $this->expectException(TourCastException::class);
        $this->expectExceptionCode(400);

        $service->getTours();
    }
}
