<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Task;
use App\Support\PlanningWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request, PlanningWindow $window): View|RedirectResponse
    {
        $week = $request->has('week') && $request->query('week') === null
            ? null : $window->resolveWeek($request->query('week'));
        if ($week === null) {
            return redirect()->route('tasks.index')->with('warning', 'That week is unavailable. Showing the current week.');
        }

        $tasks = Task::query()
            ->whereBetween('scheduled_date', [$week->toDateString(), $week->addDays(6)->toDateString()])
            ->orderBy('is_completed')
            ->orderByRaw("CASE WHEN is_completed = 1 THEN 0 WHEN priority = 'high' THEN 0 WHEN priority = 'medium' THEN 1 ELSE 2 END")
            ->orderBy('created_at')->orderBy('id')->get();

        $grouped = $tasks->groupBy(fn (Task $task) => $task->scheduled_date->toDateString());
        $days = collect(range(0, 6))->map(function (int $offset) use ($week, $window, $grouped) {
            $date = $week->addDays($offset);
            $dayTasks = $grouped->get($date->toDateString(), collect());

            return ['date' => $date, 'available' => $window->contains($date),
                'tasks' => $dayTasks, 'planned_hours' => $dayTasks->sum('estimated_hours')];
        });

        $dateOptions = collect();
        for ($date = $window->firstWeek; $date->lte($window->lastWeek->addDays(6)); $date = $date->addDay()) {
            $dateOptions->push($date);
        }

        // Keep an edit form available after validation even if its task is outside the visible week.
        $failedTask = null;
        if ($request->session()->has('errors') && ctype_digit((string) old('_task_id', ''))) {
            $failedTask = Task::find(old('_task_id'));
        }
        $modalTasks = $failedTask ? $tasks->push($failedTask)->unique('id') : $tasks;

        return view('tasks.index', compact('window', 'week', 'days', 'dateOptions', 'modalTasks'));
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $task = Task::create($request->validated());

        return redirect()->route('tasks.index', ['week' => $task->scheduled_date->startOfWeek()->toDateString()])
            ->with('success', 'Task added.');
    }

    public function update(TaskRequest $request, Task $task, PlanningWindow $window): RedirectResponse
    {
        $task->fill($request->validated());
        $moved = $task->isDirty('scheduled_date');
        $task->save();
        $week = $moved ? $task->scheduled_date->startOfWeek() : $window->resolveWeek($request->input('week'));

        return redirect()->route('tasks.index', ['week' => ($week ?? $window->firstWeek)->toDateString()])
            ->with('success', 'Task updated.');
    }

    public function destroy(Request $request, Task $task, PlanningWindow $window): RedirectResponse
    {
        $task->delete();

        return $this->returnToWeek($request, $window, 'Task deleted.');
    }

    public function toggle(Request $request, Task $task, PlanningWindow $window): RedirectResponse
    {
        $task->is_completed = ! $task->is_completed;
        $task->save();

        return $this->returnToWeek($request, $window, $task->is_completed ? 'Task completed.' : 'Task reopened.');
    }

    private function returnToWeek(Request $request, PlanningWindow $window, string $message): RedirectResponse
    {
        $week = $window->resolveWeek($request->input('week')) ?? $window->firstWeek;

        return redirect()->route('tasks.index', ['week' => $week->toDateString()])->with('success', $message);
    }
}
