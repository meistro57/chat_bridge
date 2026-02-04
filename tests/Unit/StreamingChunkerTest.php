<?php

namespace Tests\Unit;

use App\Services\AI\StreamingChunker;
use Tests\TestCase;

class StreamingChunkerTest extends TestCase
{
    public function test_it_splits_strings_by_limit(): void
    {
        $chunker = new StreamingChunker;

        $pieces = $chunker->split('abcdefghij', 3);

        $this->assertSame(['abc', 'def', 'ghi', 'j'], $pieces);
    }
}
