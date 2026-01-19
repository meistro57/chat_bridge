<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\Data\MessageData;
use Illuminate\Support\Collection;

interface AIDriverInterface
{
    /**
     * @param  Collection<int, MessageData>  $messages
     */
    public function chat(Collection $messages, float $temperature = 0.7): string;

    /**
     * @param  Collection<int, MessageData>  $messages
     * @return iterable<string>
     */
    public function streamChat(Collection $messages, float $temperature = 0.7): iterable;
}
