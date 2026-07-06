<?php

namespace Tests\Unit;

use App\Models\OrchestratorRun;
use App\Models\OrchestratorStep;
use App\Services\Orchestrator\OrchestratorService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class OrchestratorServiceTest extends TestCase
{
    private OrchestratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrchestratorService;
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

    public function test_evaluate_condition_invalid_regex_returns_false(): void
    {
        $this->assertFalse($this->service->evaluateCondition(['regex' => '['], 'Request was approved.'));
    }

    public function test_resolve_step_input_serializes_array_variables(): void
    {
        $step = new OrchestratorStep([
            'input_source' => 'variable',
            'input_variable_name' => 'research.findings',
        ]);

        $run = new OrchestratorRun([
            'variables' => [
                'research' => [
                    'findings' => ['approved' => true],
                ],
            ],
        ]);

        $this->assertSame('{"approved":true}', $this->service->resolveStepInput($step, $run));
    }

    public function test_compute_next_run_at_returns_carbon_instance(): void
    {
        $this->assertInstanceOf(Carbon::class, $this->service->computeNextRunAt('* * * * *', 'UTC'));
    }
}
