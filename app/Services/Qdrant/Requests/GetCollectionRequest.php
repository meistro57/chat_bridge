<?php

namespace App\Services\Qdrant\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCollectionRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $collectionName,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}";
    }
}
