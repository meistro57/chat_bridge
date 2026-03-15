<?php

namespace Tests\Feature;

use App\Models\Orchestration;
use App\Models\OrchestratorRun;
use App\Models\OrchestratorStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrchestratorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->get(route('orchestrator.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_lists_user_orchestrations(): void
    {
        $user = User::factory()->create();
        $orchestration = Orchestration::factory()->create(['user_id' => $user->id]);
        $other = Orchestration::factory()->create();

        $this->actingAs($user)
            ->get(route('orchestrator.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orchestrator/Index')
                ->has('orchestrations.data', 1)
                ->where('orchestrations.data.0.id', $orchestration->id)
            );
    }

    public function test_show_returns_orchestration_detail(): void
    {
        $user = User::factory()->create();
        $orchestration = Orchestration::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('orchestrator.show', $orchestration->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orchestrator/Show')
                ->where('orchestration.id', $orchestration->id)
            );
    }

    public function test_show_forbids_other_users(): void
    {
        $user = User::factory()->create();
        $orchestration = Orchestration::factory()->create();

        $this->actingAs($user)
            ->get(route('orchestrator.show', $orchestration->id))
            ->assertForbidden();
    }

    public function test_store_creates_orchestration_with_steps(): void
    {
        $user = User::factory()->create();

        $payload = [
            'name' => 'My Pipeline',
            'description' => 'A test pipeline',
            'goal' => 'Test goal',
            'is_scheduled' => false,
            'steps' => [
                [
                    'step_number' => 1,
                    'label' => 'First Step',
                    'input_source' => 'static',
                    'input_value' => 'Hello world',
                    'output_action' => 'log',
                    'pause_before_run' => false,
                ],
            ],
        ];

        $this->actingAs($user)
            ->post(route('orchestrator.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('orchestrations', [
            'user_id' => $user->id,
            'name' => 'My Pipeline',
        ]);

        $orchestration = Orchestration::where('user_id', $user->id)->first();
        $this->assertDatabaseHas('orchestration_steps', [
            'orchestration_id' => $orchestration->id,
            'label' => 'First Step',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('orchestrator.store'), [])
            ->assertSessionHasErrors(['name', 'steps']);
    }

    public function test_destroy_soft_deletes_orchestration(): void
    {
        $user = User::factory()->create();
        $orchestration = Orchestration::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('orchestrator.destroy', $orchestration->id))
            ->assertRedirect(route('orchestrator.index'));

        $this->assertSoftDeleted('orchestrations', ['id' => $orchestration->id]);
    }

    public function test_destroy_forbids_other_users(): void
    {
        $user = User::factory()->create();
        $orchestration = Orchestration::factory()->create();

        $this->actingAs($user)
            ->delete(route('orchestrator.destroy', $orchestration->id))
            ->assertForbidden();
    }

    public function test_run_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $orchestration = Orchestration::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('orchestrator.run', $orchestration->id))
            ->assertRedirect(route('orchestrator.show', $orchestration->id));

        $this->assertDatabaseHas('orchestrator_runs', [
            'orchestration_id' => $orchestration->id,
            'user_id' => $user->id,
            'triggered_by' => 'manual',
        ]);

        Queue::assertPushed(\App\Jobs\RunOrchestration::class);
    }

    public function test_run_forbids_other_users(): void
    {
        $user = User::factory()->create();
        $orchestration = Orchestration::factory()->create();

        $this->actingAs($user)
            ->post(route('orchestrator.run', $orchestration->id))
            ->assertForbidden();
    }

    public function test_resume_forbids_other_users(): void
    {
        $user = User::factory()->create();
        $run = OrchestratorRun::factory()->create(['status' => 'paused']);

        $this->actingAs($user)
            ->post(route('orchestrator.resume', $run->id))
            ->assertForbidden();
    }

    public function test_wizard_page_loads(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('orchestrator.wizard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Orchestrator/Wizard'));
    }

    public function test_wizard_chat_validates_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('orchestrator.wizard.chat'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);
    }

    public function test_runs_index_lists_runs_for_orchestration(): void
    {
        $user = User::factory()->create();
        $orchestration = Orchestration::factory()->create(['user_id' => $user->id]);
        $run = OrchestratorRun::factory()->create(['orchestration_id' => $orchestration->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('orchestrator.runs.index', $orchestration->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orchestrator/Runs/Index')
                ->has('runs.data', 1)
            );
    }

    public function test_run_show_displays_step_runs(): void
    {
        $user = User::factory()->create();
        $orchestration = Orchestration::factory()->create(['user_id' => $user->id]);
        $run = OrchestratorRun::factory()->create(['orchestration_id' => $orchestration->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('orchestrator.runs.show', $run->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orchestrator/Runs/Show')
                ->where('run.id', $run->id)
            );
    }
}
