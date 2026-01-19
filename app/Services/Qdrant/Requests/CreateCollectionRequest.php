<?php

namespace App\Services\Qdrant\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateCollectionRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        protected string $collectionName,
        protected int $vectorSize,
        protected string $distance = 'Cosine',
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}";
    }

    protected function defaultBody(): array
    {
        return [
            'vectors' => [
                'size' => $this->vectorSize,
                'distance' => $this->distance,
            ],
        ];
    }
}
