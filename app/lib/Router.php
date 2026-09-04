<?php
/**
 * 最小限のルーター。
 * ライブラリを足さない方針なので自前。{id} のようなプレースホルダだけ対応する。
 */
declare(strict_types=1);

final class Router
{
    /** @var array<int, array{method:string, regex:string, keys:array, handler:callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /** GET も POST も同じ処理でよい画面用 */
    public function any(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $keys  = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '([^/]+)';
        }, $path);

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#',
            'keys'    => $keys,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }

            array_shift($m);
            $params = $route['keys'] ? array_combine($route['keys'], $m) : [];
            ($route['handler'])($params);
            return;
        }

        if ($pathMatched) {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        http_response_code(404);
        render_error(404, 'ページが見つかりません', $path);
    }
}
