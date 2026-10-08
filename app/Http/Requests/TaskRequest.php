<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Support\PlanningWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->title) ? trim($this->title) : $this->title,
            'priority' => $this->input('priority', 'medium'),
        ]);
    }

    public function rules(PlanningWindow $window): array
    {
        $task = $this->route('task');
        $scheduledRules = ['bail', 'required', 'date_format:Y-m-d'];

        if (! $task instanceof Task || $this->input('scheduled_date') !== $task->scheduled_date->toDateString()) {
            $scheduledRules[] = 'after_or_equal:'.$window->today->toDateString();
            $scheduledRules[] = 'before_or_equal:'.$window->end->toDateString();
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'estimated_hours' => ['bail', 'required', 'numeric', 'min:0.5', 'max:24', 'multiple_of:0.5'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'scheduled_date' => $scheduledRules,
            'due_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:scheduled_date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'estimated_hours.multiple_of' => 'Use half-hour increments, such as 0.5, 1, or 1.5.',
            'scheduled_date.after_or_equal' => 'Choose a scheduled date within the current planning window.',
            'scheduled_date.before_or_equal' => 'Choose a scheduled date within the current planning window.',
            'due_date.after_or_equal' => 'The deadline must be on or after the scheduled date.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        $window = new PlanningWindow;
        $week = $window->resolveWeek($this->input('week')) ?? $window->firstWeek;

        return route('tasks.index', ['week' => $week->toDateString()]);
    }
}
