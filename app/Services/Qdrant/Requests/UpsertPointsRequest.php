<?php

namespace App\Services\Qdrant\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpsertPointsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        protected string $collectionName,
        protected array $points,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}/points";
    }

    protected function defaultBody(): array
    {
        return [
            'points' => $this->points,
        ];
    }
}
