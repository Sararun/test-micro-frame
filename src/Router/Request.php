<?php

namespace Src\Router;
#[\AllowDynamicProperties]
class Request
{
    /**
     * @var array<string, string>
     */
    private array $query;

    /**
     * @var array<string, mixed>
     */
    private array $request;

    /**
     * @var array<string, string>
     */
    private array $server;

    /**
     * @var array<string, mixed>
     */
    private array $files;

    /**
     * @var array<string, string>
     */
    private array $params = [];

    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    private ?string $content;

    public function __construct(
        array $query,
        array $request,
        array $server,
        array $files
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->server = $server;
        $this->files = $files;
    }

    public static function create(): static
    {
        $request = new static($_GET, $_POST, $_SERVER, $_FILES);
        $request->content = file_get_contents('php://input');
        $contentType = $request->server['HTTP_CONTENT_TYPE'] ?? '';
        $requestMethod = $request->server['REQUEST_METHOD'] ?? 'GET';

        if (str_starts_with($contentType, 'application/x-www-form-urlencoded')
            && \in_array(strtoupper($requestMethod), ['PUT', 'DELETE', 'PATCH'])
        ) {
            parse_str($request->content, $data);
            $request->request = $data;
        }

        if (str_starts_with($contentType, 'application/json')) {
            $request->request = (array)json_decode($request->content ?: '[]', true);
        }

        return $request;
    }


    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }


    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->request, $this->params);
    }

    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }
        return $result;
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return isset($this->request[$key]) || isset($this->query[$key]);
    }

    public function filled(string $key): bool
    {
        return !empty($this->input($key));
    }


    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function getQueryParams(): array
    {
        return $this->query;
    }


    public function post(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $default;
    }

    public function getBodyParams(): array
    {
        return $this->request;
    }


    public function file(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]);
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$key] ?? $default;
    }

    public function getMethod(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function getUri(): string
    {
        return parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }
}