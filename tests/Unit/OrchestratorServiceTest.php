<?php

namespace Tests\Unit;

use App\Services\Orchestrator\OrchestratorService;
use PHPUnit\Framework\TestCase;

class OrchestratorServiceTest extends TestCase
{
    private OrchestratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrchestratorService();
    }

    public function test_evaluate_condition_returns_true_when_null(): void
    {
        $this->assertTrue($this->service->evaluateCondition(null, 'any output'));
    }

    public function test_evaluate_condition_returns_true_when_empty(): void
    {
        $this->assertTrue($this->service->evaluateCondition([], 'any output'));
    }

    public function test_evaluate_condition_contains_match(): void
    {
        $this->assertTrue($this->service->evaluateCondition(['contains' => 'approved'], 'Request was approved by admin.'));
    }

    public function test_evaluate_condition_contains_no_match(): void
    {
        $this->assertFalse($this->service->evaluateCondition(['contains' => 'denied'], 'Request was approved.'));
    }

    public function test_evaluate_condition_not_contains_match(): void
    {
        $this->assertTrue($this->service->evaluateCondition(['not_contains' => 'denied'], 'Request was approved.'));
    }

    public function test_evaluate_condition_not_contains_no_match(): void
    {
        $this->assertFalse($this->service->evaluateCondition(['not_contains' => 'denied'], 'Request was denied.'));
    }

    public function test_evaluate_condition_equals_match(): void
    {
        $this->assertTrue($this->service->evaluateCondition(['equals' => 'yes'], 'yes'));
    }

    public function test_evaluate_condition_equals_no_match(): void
    {
        $this->assertFalse($this->service->evaluateCondition(['equals' => 'yes'], 'no'));
    }

    public function test_evaluate_condition_regex_match(): void
    {
        $this->assertTrue($this->service->evaluateCondition(['regex' => 'approve[ds]'], 'Request was approved.'));
    }

    public function test_evaluate_condition_regex_no_match(): void
    {
        $this->assertFalse($this->service->evaluateCondition(['regex' => '^denied'], 'Request was approved.'));
    }

    public function test_evaluate_condition_unknown_key_returns_true(): void
    {
        $this->assertTrue($this->service->evaluateCondition(['unknown_key' => 'value'], 'some output'));
    }

    public function test_evaluate_condition_with_null_previous_output(): void
    {
        $this->assertFalse($this->service->evaluateCondition(['contains' => 'approved'], null));
    }
}
