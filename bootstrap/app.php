<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureStudentAuthenticated;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureSystemOwner;
use App\Http\Middleware\EnsureTeacher;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'permission' => CheckPermission::class,
            'super-admin' => EnsureSuperAdmin::class,
            'owner' => EnsureSystemOwner::class,
            'student.auth' => EnsureStudentAuthenticated::class,
            'teacher' => EnsureTeacher::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // File đã bị xoá khỏi ổ đĩa (tài liệu, ảnh, audio…) → 404 thay vì lỗi máy chủ 500.
        $exceptions->map(FileNotFoundException::class,
            fn ($e) => new NotFoundHttpException('Tệp không còn trên máy chủ.', $e));
    })->create();
