<?php
// src/core/Router.php
class Router
{
    private $routes = [];
    private $beforeMiddleware = null;

    public function get($path, $handler)
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post($path, $handler)
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function before($middleware)
    {
        $this->beforeMiddleware = $middleware;
    }

    public function dispatch($path)
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Run before middleware if exists
        if ($this->beforeMiddleware) {
            call_user_func($this->beforeMiddleware);
        }

        if (isset($this->routes[$method][$path])) {
            $handler = $this->routes[$method][$path];
            list($controller, $method) = explode('@', $handler);

            require_once __DIR__ . "/../controllers/$controller.php";
            $controllerInstance = new $controller();
            $controllerInstance->$method();
        } else {
            http_response_code(404);
            echo 'Page not found';
        }
    }
}
