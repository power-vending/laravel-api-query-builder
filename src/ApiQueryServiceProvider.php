<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PowerVending\LaravelApiQueryBuilder\Http\Controllers\SchemaController;

class ApiQueryServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/api-query-builder.php', 'api-query-builder');
    }

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->registerConfiguredRoutes();

        $this->publishes([
            __DIR__ . '/../config/api-query-builder.php' => config_path('api-query-builder.php'),
        ], 'api-query-builder-config');
    }

    private function registerConfiguredRoutes(): void
    {
        $default_routes = [
            'api.query_builder.schema.show' => [
                'method' => 'get',
                'uri' => 'api-query-builder/{resource}/schema',
                'action' => [SchemaController::class, 'show'],
                'middlewares' => ['api'],
            ],
        ];

        $routes = config('api-query-builder.routes', $default_routes);

        if (!is_array($routes)) {
            $routes = $default_routes;
        }

        foreach ($routes as $name => $route_config) {
            if (!is_array($route_config)) {
                continue;
            }

            if (array_key_exists('uri', $route_config)) {
                $this->registerSingleRoute((string) $name, $route_config);

                continue;
            }

            $prefix = $route_config['prefix'] ?? '';
            $middlewares = $route_config['middlewares'] ?? ['api'];

            $group = Route::middleware($middlewares)->name((string) $name);

            if ($prefix !== '') {
                $group = $group->prefix($prefix);
            }

            $group->group(__DIR__ . '/../routes/api.php');
        }
    }

    private function registerSingleRoute(string $name, array $route_config): void
    {
        $method = strtolower((string) ($route_config['method'] ?? 'get'));
        $uri = (string) ($route_config['uri'] ?? '');
        $action = $route_config['action'] ?? null;
        $middlewares = $route_config['middlewares'] ?? ['api'];

        if ($uri === '' || $action === null) {
            return;
        }

        if (!$this->isValidPackageAction($action)) {
            return;
        }

        $route = match ($method) {
            'get' => Route::get($uri, $action),
            'post' => Route::post($uri, $action),
            'put' => Route::put($uri, $action),
            'patch' => Route::patch($uri, $action),
            'delete' => Route::delete($uri, $action),
            'options' => Route::options($uri, $action),
            default => Route::match([strtoupper($method)], $uri, $action),
        };

        $route->middleware($middlewares)->name($name);
    }

    private function isValidPackageAction(mixed $action): bool
    {
        if (!is_array($action) || count($action) !== 2) {
            return false;
        }

        [$controller_class, $method] = $action;

        if (!is_string($controller_class) || !is_string($method)) {
            return false;
        }

        $package_namespace = 'PowerVending\\LaravelApiQueryBuilder\\Http\\Controllers\\';

        if (!str_starts_with($controller_class, $package_namespace)) {
            return false;
        }

        return class_exists($controller_class) && method_exists($controller_class, $method);
    }
}
