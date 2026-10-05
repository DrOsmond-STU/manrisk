<?php

namespace Tests\Unit;

use App\Support\Scoring;
use PHPUnit\Framework\TestCase;

class ScoringTest extends TestCase
{
    public function test_level_thresholds(): void
    {
        $this->assertSame('low', Scoring::levelFromScore(4));
        $this->assertSame('medium', Scoring::levelFromScore(5));
        $this->assertSame('medium', Scoring::levelFromScore(9));
        $this->assertSame('high', Scoring::levelFromScore(10));
        $this->assertSame('very_high', Scoring::levelFromScore(16));
        $this->assertCount(25, Scoring::defaultMatrix());
    }

    public function test_evaluation_order(): void
    {
        $s = new Scoring(new \App\Models\CriteriaVersion(['matrix' => Scoring::defaultMatrix(), 'thresholds' => ['escalate' => 16, 'critical' => 20]]));
        $this->assertSame('critical', $s->evaluate(20, 6, 9));
        $this->assertSame('escalate', $s->evaluate(16, 6, 9));
        $this->assertSame('treat', $s->evaluate(12, 6, 9));
        $this->assertSame('monitor', $s->evaluate(8, 6, 9));
        $this->assertSame('acceptable', $s->evaluate(6, 6, 9));
        $this->assertSame(5, Scoring::impactFromDims(['fin' => 2, 'ops' => 5, 'rep' => null], 3));
        $this->assertSame(3, Scoring::impactFromDims([], 3));
        $this->assertSame(25, $s->score(9, 9));
    }
}
