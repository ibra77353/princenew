<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * موجّه بسيط يدعم المسارات الثابتة والمعاملات {id} والحماية بالمصادقة.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:array{0:class-string,1:string},middleware:array<int,string>}> */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler @param array<int,string> $middleware */
    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /** @param array{0:class-string,1:string} $handler @param array<int,string> $middleware */
    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /** @param array{0:class-string,1:string} $handler @param array<int,string> $middleware */
    private function add(string $method, string $pattern, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => $this->toRegex($pattern),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    private function toRegex(string $pattern): string
    {
        $escaped = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);

        return '#^' . $escaped . '$#';
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        // احترام التثبيت داخل مجلد فرعي
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        $path = '/' . trim($path, '/');
        if ($path === '/') {
            $path = '/';
        }

        $method = strtoupper($method);
        $allowedOther = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            if ($route['method'] !== $method) {
                if ($method === 'HEAD' && $route['method'] === 'GET') {
                    // يُسمح بطلبات HEAD على مسارات GET
                } else {
                    $allowedOther = true;
                    continue;
                }
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }

            $this->run($route['handler'], $route['middleware'], $params);

            return;
        }

        if ($allowedOther) {
            Response::abort(405, 'طريقة الطلب غير مسموحة');
        }

        Response::abort(404, 'الصفحة غير موجودة');
    }

    /** @param array{0:class-string,1:string} $handler @param array<int,string> $middleware @param array<string,string> $params */
    private function run(array $handler, array $middleware, array $params): void
    {
        foreach ($middleware as $name) {
            if ($name === 'auth') {
                Auth::requireLogin();
            }
            // الحماية الإدارية على الخادم: كل مسار للمدير فقط يُرفض لغير المدير (403) حتى لو أُرسل الطلب مباشرة (M6)
            if ($name === 'admin') {
                Auth::requireLogin();
                Auth::requireAdmin();
            }
            if ($name === 'guest' && Auth::check() && !Auth::needsSetup()) {
                Response::redirect('/');
            }
        }

        [$class, $action] = $handler;
        $controller = new $class();
        $controller->{$action}($params);
    }
}
