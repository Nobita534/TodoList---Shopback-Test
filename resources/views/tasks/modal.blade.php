@php
    $key = $task ? (string) $task->id : 'add';
    $failed = $errors->any() && (string) old('_task_id', 'add') === $key;
    $defaultDate = $week->max($window->today)->toDateString();
    $values = [
        'title' => $task?->title ?? '', 'description' => $task?->description ?? '',
        'estimated_hours' => $task?->estimated_hours ?? 1, 'priority' => $task?->priority ?? 'medium',
        'scheduled_date' => $task?->scheduled_date->toDateString() ?? $defaultDate,
        'due_date' => $task?->due_date->toDateString() ?? $defaultDate, 'notes' => $task?->notes ?? '',
    ];
    if ($failed) {
        foreach ($values as $field => $value) { $values[$field] = old($field, $value); }
    }
@endphp
<div class="modal" id="task-{{ $key }}" tabindex="-1" aria-labelledby="modal-title-{{ $key }}" aria-hidden="true" @if ($failed) data-reopen="true" @endif>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content task-form" method="post" action="{{ $task ? route('tasks.update', $task) : route('tasks.store') }}">
            @csrf
            @if ($task) @method('PUT') @endif
            <input type="hidden" name="week" value="{{ $week->toDateString() }}">
            <input type="hidden" name="_task_id" value="{{ $key }}">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="modal-title-{{ $key }}">{{ $task ? 'Edit Task' : 'Add Task' }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @foreach (['title' => 'Title', 'description' => 'Description', 'estimated_hours' => 'Duration (hours)', 'priority' => 'Priority', 'scheduled_date' => 'Scheduled date', 'due_date' => 'Deadline', 'notes' => 'Notes'] as $field => $label)
                    @php($invalid = $failed && $errors->has($field))
                    <div class="mb-3">
                        <label class="form-label" for="{{ $key }}-{{ $field }}">{{ $label }} @if (in_array($field, ['description', 'notes']))<span class="text-secondary small">(optional)</span>@endif</label>
                        @if (in_array($field, ['description', 'notes']))
                            <textarea class="form-control {{ $invalid ? 'is-invalid' : '' }}" id="{{ $key }}-{{ $field }}" name="{{ $field }}" rows="2" @if ($invalid) aria-invalid="true" aria-describedby="{{ $key }}-{{ $field }}-error" @endif>{{ $values[$field] }}</textarea>
                        @elseif ($field === 'priority')
                            <select class="form-select {{ $invalid ? 'is-invalid' : '' }}" id="{{ $key }}-{{ $field }}" name="{{ $field }}" required @if ($invalid) aria-invalid="true" aria-describedby="{{ $key }}-{{ $field }}-error" @endif>
                                @foreach (['low', 'medium', 'high'] as $priority)<option value="{{ $priority }}" @selected($values[$field] === $priority)>{{ ucfirst($priority) }}</option>@endforeach
                            </select>
                        @elseif ($field === 'scheduled_date')
                            <select class="form-select scheduled-date {{ $invalid ? 'is-invalid' : '' }}" id="{{ $key }}-{{ $field }}" name="{{ $field }}" required @if ($invalid) aria-invalid="true" aria-describedby="{{ $key }}-{{ $field }}-error" @endif>
                                @if ($task && ! $dateOptions->contains(fn ($date) => $date->isSameDay($task->scheduled_date)))
                                    <option value="{{ $task->scheduled_date->toDateString() }}" @selected($values[$field] === $task->scheduled_date->toDateString())>{{ $task->scheduled_date->format('l, d M Y') }} (original date)</option>
                                @endif
                                @foreach ($dateOptions as $date)
                                    @php($original = $task && $date->isSameDay($task->scheduled_date))
                                    <option value="{{ $date->toDateString() }}" @selected($values[$field] === $date->toDateString()) @disabled(! $window->contains($date) && ! $original)>{{ $date->format('l, d M Y') }}{{ ! $window->contains($date) ? ($original ? ' (original date)' : ' — unavailable') : '' }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ $task ? 'Keep the original date or choose a date within the planning window.' : 'Unavailable dates cannot receive new tasks.' }}</div>
                        @else
                            <input class="form-control {{ $invalid ? 'is-invalid' : '' }}" id="{{ $key }}-{{ $field }}" name="{{ $field }}" value="{{ $values[$field] }}" required
                                type="{{ ['title' => 'text', 'estimated_hours' => 'number', 'due_date' => 'date'][$field] }}"
                                @if ($field === 'title') maxlength="255" @elseif ($field === 'estimated_hours') min="0.5" max="24" step="0.5" @else min="{{ $values['scheduled_date'] }}" @endif
                                @if ($invalid) aria-invalid="true" aria-describedby="{{ $key }}-{{ $field }}-error" @endif>
                            @if ($field === 'estimated_hours')<div class="form-text">0.5–24 hours, in half-hour increments.</div>@endif
                        @endif
                        @if ($invalid)<div class="invalid-feedback" id="{{ $key }}-{{ $field }}-error">{{ $errors->first($field) }}</div>@endif
                    </div>
                @endforeach
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">{{ $task ? 'Save changes' : 'Add task' }}</button>
            </div>
        </form>
    </div>
</div>
