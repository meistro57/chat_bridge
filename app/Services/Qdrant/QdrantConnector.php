<?php

namespace App\Services\Qdrant;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class QdrantConnector extends Connector
{
    use AcceptsJson;

    protected ?int $connectTimeout = 10;

    protected ?int $requestTimeout = 30;

    public function __construct(
        protected string $host = 'localhost',
        protected int $port = 6333,
    ) {}

    public function resolveBaseUrl(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
