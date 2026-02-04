<?php

namespace App\Services\AI;

class StreamingChunker
{
    /**
     * @return array<int, string>
     */
    public function split(string $chunk, int $limit): array
    {
        if ($limit <= 0) {
            return [$chunk];
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            $length = mb_strlen($chunk, 'UTF-8');
            if ($length <= $limit) {
                return [$chunk];
            }

            $pieces = [];
            for ($offset = 0; $offset < $length; $offset += $limit) {
                $pieces[] = mb_substr($chunk, $offset, $limit, 'UTF-8');
            }

            return $pieces;
        }

        return str_split($chunk, $limit);
    }
}
