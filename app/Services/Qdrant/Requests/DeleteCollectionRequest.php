<?php

namespace App\Services\Qdrant\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteCollectionRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $collectionName,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}";
    }
}
