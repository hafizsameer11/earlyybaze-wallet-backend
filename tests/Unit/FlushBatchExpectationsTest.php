<?php

namespace Tests\Unit;

use App\Services\FlushBatchExpectations;
use Tests\TestCase;

class FlushBatchExpectationsTest extends TestCase
{
    public function test_format_batch_amount_sums_row_amounts(): void
    {
        $assets = collect([
            (object) ['amount' => '0.02396017', 'transfered_amount' => null],
            (object) ['amount' => '0.02396017', 'transfered_amount' => null],
            (object) ['amount' => '0.01000000', 'transfered_amount' => '0.01000000'],
        ]);

        $this->assertSame('0.05792034', FlushBatchExpectations::formatBatchAmount($assets));
    }
}
