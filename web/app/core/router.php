<?php
declare(strict_types=1);

class Router {
  private array $routes = [];

  public function get(string $path, callable $handler): void { $this->map('GET', $path, $handler); }
  public function post(string $path, callable $handler): void { $this->map('POST', $path, $handler); }

  private function map(string $method, string $path, callable $handler): void {
    $this->routes[] = [$method, $path, $handler];
  }

  public function dispatch(string $method, string $path): void {
    foreach($this->routes as [$m, $p, $h]) {
      if($m !== $method) continue;
      if($this->match($p, $path)) { $h(); return; }
    }
    http_response_code(404);
    echo "404";
  }

  private function match(string $route, string $path): bool {
    if($route === $path) return true;
    // wildcard prefix: /public/*
    if(str_ends_with($route, '/*')) {
      $prefix = substr($route, 0, -1);
      return str_starts_with($path, rtrim($prefix,'*'));
    }
    return false;
  }
}
