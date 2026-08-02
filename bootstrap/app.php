<?php

use App\Http\Middleware\EnforceProductionHttps;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureTwoFactorAuthentication;
use App\Http\Middleware\HandleLegacyRedirects;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnforceProductionHttps::class);
        $middleware->append(HandleLegacyRedirects::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'admin' => EnsureAdminAccess::class,
            '2fa.confirmed' => EnsureTwoFactorAuthentication::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

$app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
    $mysql = $app['config']->get('database.connections.mysql');

    if (! is_array($mysql)) {
        throw new RuntimeException('The required MySQL database connection is not configured.');
    }

    $app['config']->set('database.default', 'mysql');
    $app['config']->set('database.connections', ['mysql' => $mysql]);
});

return $app;
