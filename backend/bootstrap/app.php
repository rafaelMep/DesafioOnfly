<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Sessão inválida ou expirada. Entre novamente.'], 401);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            $messages = [
                403 => 'Você não tem permissão para realizar esta ação.',
                404 => 'O recurso solicitado não foi encontrado.',
                429 => 'Muitas tentativas. Aguarde um momento e tente novamente.',
            ];
            if ($request->is('api/*') && isset($messages[$exception->getStatusCode()])) {
                return response()->json(['message' => $messages[$exception->getStatusCode()]], $exception->getStatusCode(), $exception->getHeaders());
            }
        });
    })->create();
