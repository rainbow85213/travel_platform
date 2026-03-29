<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TourCastService
{
    private Client $client;

    private ?Client $scheduleClient = null;

    public function __construct(?Client $client = null)
    {
        if ($client === null && empty(config('services.tourcast.api_key'))) {
            Log::warning('TourCastService: TOURCAST_API_KEY가 설정되지 않았습니다. API 요청 시 인증 오류가 발생할 수 있습니다.');
        }

        $this->client = $client ?? $this->buildClient();
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * 투어 목록 조회
     *
     * @param  array<string, mixed> $params  page, per_page, category 등
     * @return array<string, mixed>
     *
     * @throws TourCastException
     */
    public function getTours(array $params = []): array
    {
        return $this->get('/tours', $params);
    }

    /**
     * 투어 상세 조회
     *
     * @return array<string, mixed>
     *
     * @throws TourCastException
     */
    public function getTour(string|int $id): array
    {
        return $this->get("/tours/{$id}");
    }

    /**
     * 투어 검색
     *
     * @param  array<string, mixed> $params  추가 필터
     * @return array<string, mixed>
     *
     * @throws TourCastException
     */
    public function searchTours(string $keyword, array $params = []): array
    {
        return $this->get('/tours/search', array_merge(['q' => $keyword], $params));
    }

    // =========================================================================
    // Schedule Proxy (TourCast /api/schedule/*)
    // =========================================================================

    /**
     * TourCast GET /api/schedule/{$path} 프록시
     *
     * @param  array<string, mixed> $query
     * @return array<string, mixed>
     *
     * @throws TourCastException
     */
    public function proxyScheduleGet(string $path, array $query = []): array
    {
        return $this->scheduleGet($path, $query);
    }

    /**
     * TourCast POST /api/schedule/{$path} 프록시
     *
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     *
     * @throws TourCastException
     */
    public function proxySchedulePost(string $path, array $body = []): array
    {
        return $this->schedulePost($path, $body);
    }

    /**
     * TourCast PATCH /api/schedule/{$path} 프록시
     *
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     *
     * @throws TourCastException
     */
    public function proxySchedulePatch(string $path, array $body = []): array
    {
        return $this->schedulePatch($path, $body);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * @param  array<string, mixed> $query
     * @return array<string, mixed>
     *
     * @throws TourCastException
     */
    private function get(string $uri, array $query = []): array
    {
        try {
            $options  = $query ? ['query' => $query] : [];
            $response = $this->client->get($uri, $options);

            return json_decode($response->getBody()->getContents(), true) ?? [];

        } catch (ConnectException $e) {
            Log::error('TourCast API 연결 실패', [
                'uri'     => $uri,
                'message' => $e->getMessage(),
            ]);

            throw new TourCastException(
                'TourCast API에 연결할 수 없습니다: ' . $e->getMessage(),
                0,
                $e
            );

        } catch (RequestException $e) {
            $status  = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $body    = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();

            Log::error('TourCast API 요청 실패', [
                'uri'     => $uri,
                'status'  => $status,
                'body'    => $body,
            ]);

            throw new TourCastException(
                "TourCast API 오류 (HTTP {$status}): {$body}",
                $status,
                $e
            );
        }
    }

    /**
     * @param  array<string, mixed> $query
     * @return array<string, mixed>
     *
     * @throws TourCastException
     */
    private function scheduleGet(string $path, array $query = []): array
    {
        try {
            $uri      = ltrim($path, '/');
            $options  = $query ? ['query' => $query] : [];
            $response = $this->getScheduleClient()->get($uri, $options);

            return json_decode($response->getBody()->getContents(), true) ?? [];

        } catch (ConnectException $e) {
            Log::error('TourCast Schedule API 연결 실패', ['path' => $path, 'message' => $e->getMessage()]);
            throw new TourCastException('TourCast API에 연결할 수 없습니다: ' . $e->getMessage(), 0, $e);

        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $body   = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('TourCast Schedule API 요청 실패', ['path' => $path, 'status' => $status, 'body' => $body]);
            throw new TourCastException("TourCast API 오류 (HTTP {$status}): {$body}", $status, $e);
        }
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     *
     * @throws TourCastException
     */
    private function schedulePost(string $path, array $body = []): array
    {
        try {
            $uri      = ltrim($path, '/');
            $response = $this->getScheduleClient()->post($uri, ['json' => $body]);

            return json_decode($response->getBody()->getContents(), true) ?? [];

        } catch (ConnectException $e) {
            Log::error('TourCast Schedule API 연결 실패', ['path' => $path, 'message' => $e->getMessage()]);
            throw new TourCastException('TourCast API에 연결할 수 없습니다: ' . $e->getMessage(), 0, $e);

        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $body   = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('TourCast Schedule API 요청 실패', ['path' => $path, 'status' => $status, 'body' => $body]);
            throw new TourCastException("TourCast API 오류 (HTTP {$status}): {$body}", $status, $e);
        }
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     *
     * @throws TourCastException
     */
    private function schedulePatch(string $path, array $body = []): array
    {
        try {
            $uri      = ltrim($path, '/');
            $response = $this->getScheduleClient()->patch($uri, ['json' => $body]);

            return json_decode($response->getBody()->getContents(), true) ?? [];

        } catch (ConnectException $e) {
            Log::error('TourCast Schedule API 연결 실패', ['path' => $path, 'message' => $e->getMessage()]);
            throw new TourCastException('TourCast API에 연결할 수 없습니다: ' . $e->getMessage(), 0, $e);

        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $body   = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('TourCast Schedule API 요청 실패', ['path' => $path, 'status' => $status, 'body' => $body]);
            throw new TourCastException("TourCast API 오류 (HTTP {$status}): {$body}", $status, $e);
        }
    }

    private function getScheduleClient(): Client
    {
        if ($this->scheduleClient === null) {
            $this->scheduleClient = $this->buildScheduleClient();
        }

        return $this->scheduleClient;
    }

    private function buildClient(): Client
    {
        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(
            $this->retryDecider(),
            $this->retryDelay()
        ));

        return new Client([
            'base_uri' => config('services.tourcast.base_url'),
            'timeout'  => config('services.tourcast.timeout', 10),
            'headers'  => [
                'Authorization' => 'Bearer ' . config('services.tourcast.api_key', ''),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'handler'  => $stack,
        ]);
    }

    private function buildScheduleClient(): Client
    {
        // base_url 예: https://api.tourcast.io/v1 또는 http://localhost:3000
        // schedule 경로는 /api/schedule/* — /v1 없이 루트 기준
        $baseUrl = rtrim(config('services.tourcast.base_url'), '/');
        $rootUrl = preg_replace('/\/v\d+$/', '', $baseUrl);

        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(
            $this->retryDecider(),
            $this->retryDelay()
        ));

        return new Client([
            'base_uri' => $rootUrl . '/api/schedule/',
            'timeout'  => config('services.tourcast.timeout', 10),
            'headers'  => [
                'Authorization' => 'Bearer ' . config('services.tourcast.api_key', ''),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'handler'  => $stack,
        ]);
    }

    /**
     * 재시도 여부 결정:
     * - ConnectException(연결 오류)
     * - HTTP 429(Rate Limit), 500, 502, 503, 504(서버 오류)
     */
    private function retryDecider(): callable
    {
        $maxRetries = config('services.tourcast.retry', 3);

        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response,
            ?\Throwable $exception
        ) use ($maxRetries): bool {
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
        };
    }

    /**
     * 지수 백오프: 500ms → 1,000ms → 2,000ms
     */
    private function retryDelay(): callable
    {
        return fn (int $retries): int => (int) (500 * (2 ** ($retries - 1)));
    }
}
