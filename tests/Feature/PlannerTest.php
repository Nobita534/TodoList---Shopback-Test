<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Support\PlanningWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-08 10:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Write assessment', 'description' => 'Explain the decisions',
            'estimated_hours' => '1.5', 'priority' => 'high',
            'scheduled_date' => '2026-10-08', 'due_date' => '2026-10-09',
            'notes' => 'Review before submitting', 'week' => '2026-10-05', '_task_id' => 'add',
        ], $overrides);
    }

    private function task(array $overrides = []): Task
    {
        return Task::create(collect($this->payload($overrides))->except(['week', '_task_id'])->all());
    }

    public function test_board_opens_on_current_week_with_seven_days_and_disabled_dates(): void
    {
        $response = $this->get('/')->assertOk()->assertSee('05 Oct')->assertSee('11 Oct 2026');
        $response->assertViewHas('days', fn ($days) => $days->count() === 7 && $days[0]['date']->isMonday() && $days[6]['date']->isSunday());
        $response->assertSeeInOrder(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
        $this->assertSame(7, substr_count($response->getContent(), 'data-date='));
        $this->assertMatchesRegularExpression('/value="2026-10-05"[^>]*disabled/', $response->getContent());
        $this->assertMatchesRegularExpression('/<button[^>]*disabled[^>]*>&larr; Previous Week/', $response->getContent());
    }

    public function test_create_persists_all_fields_trims_title_and_survives_reload(): void
    {
        $this->post('/tasks', $this->payload(['title' => '  Write assessment  ', 'is_completed' => true]))
            ->assertSessionHasNoErrors()->assertRedirect('/?week=2026-10-05');
        $this->assertDatabaseHas('tasks', [
            'title' => 'Write assessment', 'description' => 'Explain the decisions', 'estimated_hours' => 1.5,
            'priority' => 'high', 'scheduled_date' => '2026-10-08', 'due_date' => '2026-10-09',
            'notes' => 'Review before submitting', 'is_completed' => 0,
        ]);
        $this->get('/')->assertSee('Write assessment');
        $this->get('/')->assertSee('Write assessment');
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_priority_defaults_to_medium_and_deadline_can_exceed_window(): void
    {
        $data = $this->payload(['due_date' => '2027-01-01']);
        unset($data['priority']);
        $this->post('/tasks', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tasks', ['priority' => 'medium', 'due_date' => '2027-01-01']);
    }

    public static function invalidTasks(): array
    {
        return [
            'blank title' => ['title', '   '], 'long title' => ['title', str_repeat('a', 256)],
            'zero hours' => ['estimated_hours', 0], 'negative hours' => ['estimated_hours', -1],
            'too many hours' => ['estimated_hours', 24.5], 'quarter hour' => ['estimated_hours', 1.25],
            'not numeric' => ['estimated_hours', 'one'], 'priority' => ['priority', 'urgent'],
            'past schedule' => ['scheduled_date', '2026-10-07'], 'future schedule' => ['scheduled_date', '2026-11-09'],
            'impossible date' => ['scheduled_date', '2026-10-32'], 'early deadline' => ['due_date', '2026-10-07'],
            'missing deadline' => ['due_date', ''], 'malformed deadline' => ['due_date', 'tomorrow'],
        ];
    }

    #[DataProvider('invalidTasks')]
    public function test_invalid_creation_is_rejected(string $field, mixed $value): void
    {
        $this->post('/tasks', $this->payload([$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_window_boundaries_and_duration_limits_are_inclusive(): void
    {
        foreach ([['2026-10-08', 0.5], ['2026-11-08', 24]] as [$date, $hours]) {
            $this->post('/tasks', $this->payload(['scheduled_date' => $date, 'due_date' => $date, 'estimated_hours' => $hours]))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('tasks', 2);
    }

    public function test_update_moves_task_between_days_and_weeks_and_keeps_completion(): void
    {
        $task = $this->task();
        $task->is_completed = true;
        $task->save();
        $changes = $this->payload(['title' => 'Updated task', 'description' => 'Changed description', 'estimated_hours' => 3,
            'priority' => 'low', 'scheduled_date' => '2026-10-16', 'due_date' => '2026-10-18', 'notes' => 'Changed notes']);
        $this->put('/tasks/'.$task->id, $changes)->assertSessionHasNoErrors()->assertRedirect('/?week=2026-10-12');
        $this->assertDatabaseHas('tasks', collect($changes)->except(['week', '_task_id'])->merge(['id' => $task->id, 'is_completed' => 1])->all());
        $this->get('/?week=2026-10-05')->assertDontSee('Updated task');
        $this->get('/?week=2026-10-12')->assertViewHas('days', fn ($days) => $days[4]['tasks']->contains('id', $task->id) && $days[3]['tasks']->isEmpty());
    }

    public function test_old_schedule_can_remain_but_cannot_change_to_another_past_date(): void
    {
        $task = $this->task(['scheduled_date' => '2026-10-05', 'due_date' => '2026-10-06']);
        $this->put('/tasks/'.$task->id, $this->payload(['title' => 'Old task edited', 'scheduled_date' => '2026-10-05', 'due_date' => '2026-10-06']))->assertSessionHasNoErrors();
        $this->assertSame('Old task edited', $task->fresh()->title);
        $this->put('/tasks/'.$task->id, $this->payload(['scheduled_date' => '2026-10-07']))->assertSessionHasErrors('scheduled_date');
        $this->assertSame('2026-10-05', $task->fresh()->scheduled_date->toDateString());
    }

    public function test_delete_removes_only_target_and_preserves_selected_week(): void
    {
        $task = $this->task();
        $other = $this->task(['title' => 'Keep this']);
        $this->delete('/tasks/'.$task->id, ['week' => '2026-10-12'])->assertRedirect('/?week=2026-10-12');
        $this->assertModelMissing($task);
        $this->assertModelExists($other);
    }

    public function test_completion_is_reversible_even_for_old_tasks(): void
    {
        $task = $this->task(['scheduled_date' => '2026-09-01', 'due_date' => '2026-09-01']);
        $this->patch('/tasks/'.$task->id.'/completion', ['week' => '2026-10-12'])->assertRedirect('/?week=2026-10-12');
        $this->assertTrue($task->fresh()->is_completed);
        $this->patch('/tasks/'.$task->id.'/completion')->assertRedirect();
        $this->assertFalse($task->fresh()->is_completed);
    }

    public function test_planned_hours_include_completed_and_sorting_is_stable(): void
    {
        $low = $this->task(['title' => 'Low task', 'priority' => 'low']);
        $medium = $this->task(['title' => 'Medium task', 'priority' => 'medium']);
        $older = $this->task(['title' => 'Older high task']);
        $older->created_at = '2026-10-01 00:00:00';
        $older->save();
        $newer = $this->task(['title' => 'Newer high task']);
        $completed = $this->task(['title' => 'Completed task', 'estimated_hours' => 2]);
        $completed->is_completed = true;
        $completed->save();
        $expected = [$older->id, $newer->id, $medium->id, $low->id, $completed->id];
        $this->get('/')->assertViewHas('days', fn ($days) => $days[3]['planned_hours'] == 8 && $days[3]['tasks']->pluck('id')->all() === $expected);
    }

    public function test_navigation_preserves_all_task_records_and_blocks_invalid_weeks(): void
    {
        $this->task();
        $this->task(['scheduled_date' => '2026-10-18', 'due_date' => '2026-10-18']);
        $before = Task::all()->toArray();
        foreach (['2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02'] as $week) {
            $this->get('/?week='.$week)->assertOk();
        }
        foreach (['2026-09-28', '2026-11-09', '2026-10-08', 'invalid', '2026-02-30', ''] as $week) {
            $this->get('/?week='.$week)->assertRedirect('/')->assertSessionHas('warning');
        }
        $this->get('/?week[]=2026-10-05')->assertRedirect('/');
        $this->assertSame($before, Task::all()->toArray());
    }

    public function test_sunday_has_tasks_and_next_control_and_final_week_is_disabled(): void
    {
        $task = $this->task(['title' => 'Sunday planning', 'scheduled_date' => '2026-10-11', 'due_date' => '2026-10-11']);
        $this->get('/')->assertSee('Sunday planning')->assertSee('Next Week')
            ->assertViewHas('days', fn ($days) => $days[6]['tasks']->contains('id', $task->id));
        $response = $this->get('/?week=2026-11-02')->assertOk();
        $this->assertMatchesRegularExpression('/<button[^>]*disabled[^>]*>Next Week/', $response->getContent());
    }

    public static function monthEnds(): array
    {
        return [['2026-01-31', '2026-02-28'], ['2028-01-31', '2028-02-29'], ['2026-08-31', '2026-09-30'], ['2026-12-31', '2027-01-31']];
    }

    #[DataProvider('monthEnds')]
    public function test_month_addition_does_not_overflow(string $today, string $end): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($today, 'Asia/Ho_Chi_Minh'));
        $this->get('/')->assertViewHas('window', fn ($window) => $window->end->toDateString() === $end);
        $this->post('/tasks', $this->payload(['scheduled_date' => $end, 'due_date' => $end]))->assertSessionHasNoErrors();
        $outside = CarbonImmutable::parse($end)->addDay()->toDateString();
        $this->post('/tasks', $this->payload(['scheduled_date' => $outside, 'due_date' => $outside]))->assertSessionHasErrors('scheduled_date');
    }

    public function test_partial_last_week_remains_seven_days_and_uses_local_today(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-08 18:30:00', 'UTC'));
        $window = new PlanningWindow;
        $this->assertSame('2026-10-09', $window->today->toDateString());
        $this->get('/?week=2026-11-09')->assertViewHas('days', fn ($days) => $days->count() === 7 && $days[0]['available'] && ! $days[1]['available']);
    }

    public function test_statuses_completion_override_and_escaped_user_content(): void
    {
        $overdue = $this->task(['title' => '<script>alert(1)</script>', 'scheduled_date' => '2026-10-05', 'due_date' => '2026-10-06']);
        $due = $this->task(['due_date' => '2026-10-08']);
        $this->assertSame('overdue', $overdue->statusOn((new PlanningWindow)->today));
        $this->assertSame('due-today', $due->statusOn((new PlanningWindow)->today));
        $this->get('/')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('Overdue')->assertSee('Due today');
        $overdue->is_completed = true;
        $overdue->save();
        $this->get('/')->assertDontSee('Overdue')->assertSee('Completed');
    }

    public function test_validation_reopens_correct_modal_and_preserves_input(): void
    {
        $task = $this->task();
        $this->put('/tasks/'.$task->id, $this->payload(['_task_id' => (string) $task->id, 'title' => 'Keep my edits', 'estimated_hours' => 1.25]))
            ->assertSessionHasErrors('estimated_hours')->assertRedirect('/?week=2026-10-05');
        $response = $this->get('/?week=2026-10-05')->assertOk()->assertSee('Keep my edits')->assertSee('Use half-hour increments');
        $this->assertMatchesRegularExpression('/id="task-'.$task->id.'"[^>]*data-reopen="true"/', $response->getContent());
        $this->assertSame('Write assessment', $task->fresh()->title);
    }

    public function test_window_recalculates_after_midnight_without_removing_old_tasks(): void
    {
        $task = $this->task();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12', 'Asia/Ho_Chi_Minh'));
        $this->get('/')->assertViewHas('week', fn ($week) => $week->toDateString() === '2026-10-12')->assertDontSee('Write assessment');
        $this->assertModelExists($task);
        $this->post('/tasks', $this->payload())->assertSessionHasErrors('scheduled_date');
    }
}
