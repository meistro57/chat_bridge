<?php

namespace App\Services\Qdrant\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SearchPointsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $collectionName,
        protected array $vector,
        protected int $limit = 10,
        protected ?array $filter = null,
        protected float $scoreThreshold = 0.7,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}/points/search";
    }

    protected function defaultBody(): array
    {
        $body = [
            'vector' => $this->vector,
            'limit' => $this->limit,
            'with_payload' => true,
            'with_vector' => false,
            'score_threshold' => $this->scoreThreshold,
        ];

        if ($this->filter) {
            $body['filter'] = $this->filter;
        }

        return $body;
    }
}
