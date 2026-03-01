<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API 요청에 대한 일관된 JSON 에러 응답
        $exceptions->render(function (ValidationException $e, Request $request): mixed {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '입력값을 확인해 주세요.',
                    'data'    => null,
                    'errors'  => $e->errors(),
                ], 422);
            }
            return null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request): mixed {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '인증이 필요합니다.',
                    'data'    => null,
                ], 401);
            }
            return null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request): mixed {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '리소스를 찾을 수 없습니다.',
                    'data'    => null,
                ], 404);
            }
            return null;
        });
    })->create();
