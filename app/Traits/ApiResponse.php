<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = '성공', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    protected function created(mixed $data = null, string $message = '생성되었습니다.'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function failure(string $message = '요청을 처리할 수 없습니다.', int $status = 400, mixed $errors = null): JsonResponse
    {
        $body = [
            'success' => false,
            'message' => $message,
            'data'    => null,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    protected function notFound(string $message = '리소스를 찾을 수 없습니다.'): JsonResponse
    {
        return $this->failure($message, 404);
    }

    protected function forbidden(string $message = '접근 권한이 없습니다.'): JsonResponse
    {
        return $this->failure($message, 403);
    }
}
