<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weekly To-Do Planner</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/planner.css') }}">
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset('js/planner.js') }}" defer></script>
</head>
<body>
<div class="toast-container position-fixed top-0 end-0 p-3 planner-toasts" aria-live="polite" aria-atomic="true">
    @if (session('success'))
        <div class="toast planner-toast" role="status" aria-atomic="true" data-bs-delay="5000">
            <div class="toast-header">
                <strong class="me-auto text-success">Success</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close notification"></button>
            </div>
            <div class="toast-body">{{ session('success') }}</div>
        </div>
    @endif
</div>
<main class="container-fluid planner-shell">
    <header class="d-flex justify-content-between align-items-center gap-3 mb-4">
        <div>
            <p class="eyebrow mb-1">A LITTLE STRUCTURE FOR YOUR WEEK</p>
            <h1 class="h3 mb-0">Weekly To-Do Planner</h1>
        </div>
        <button class="btn btn-primary flex-shrink-0" data-bs-toggle="modal" data-bs-target="#task-add">+ Add Task</button>
    </header>

    @if (session('warning'))
        <div class="alert alert-warning" role="status">{{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">Your task was not saved. Please correct the highlighted fields.</div>
    @endif

    <section class="week-heading d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3" aria-label="Week navigation">
        <div>
            <h2 class="h5 mb-1">{{ $week->format('d M') }} &ndash; {{ $week->addDays(6)->format('d M Y') }}</h2>
            <p class="text-secondary small mb-0">Schedule from {{ $window->today->format('d M Y') }} through {{ $window->end->format('d M Y') }}. All dates use Vietnam time.</p>
        </div>
        @if ($week->gt($window->firstWeek))
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('tasks.index', ['week' => $week->subWeek()->toDateString()]) }}">&larr; Previous Week</a>
        @else
            <button class="btn btn-outline-secondary btn-sm" disabled>&larr; Previous Week</button>
        @endif
    </section>

    <div class="board-scroll" tabindex="0" role="region" aria-label="Weekly task board, scroll horizontally to see all seven days">
        <div class="week-board">
            @foreach ($days as $day)
                <section class="day-column {{ $day['available'] ? '' : 'day-unavailable' }} {{ $day['date']->isSameDay($window->today) ? 'day-today' : '' }}" data-date="{{ $day['date']->toDateString() }}" aria-labelledby="day-{{ $loop->index }}">
                    <header class="day-heading">
                        <div class="d-flex justify-content-between align-items-center gap-1">
                            <h3 class="h6 mb-0" id="day-{{ $loop->index }}">{{ $day['date']->format('l') }}</h3>
                            @if ($day['date']->isSameDay($window->today))<span class="today-label">Today</span>@endif
                        </div>
                        <p class="text-secondary small mt-1 mb-2">{{ $day['date']->format('d M') }}</p>
                        <p class="hours mb-0"><strong>{{ $day['planned_hours'] + 0 }}</strong> planned hours</p>
                        @unless ($day['available'])<p class="unavailable-label mb-0 mt-2">Unavailable for scheduling</p>@endunless
                    </header>
                    <div class="day-tasks">
                        @forelse ($day['tasks'] as $task)
                            @php($status = $task->statusOn($window->today))
                            <article class="task-card task-{{ $status }}" data-task-id="{{ $task->id }}">
                                <h4 class="task-title">{{ $task->title }}</h4>
                                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                    <span class="duration">{{ $task->estimated_hours + 0 }} h</span>
                                    <span class="priority priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span>
                                </div>
                                <p class="deadline mb-1">Due {{ $task->due_date->format('d M Y') }}</p>
                                @if ($status !== 'planned')
                                    <p class="task-status mb-2">{{ ['completed' => 'Completed', 'overdue' => 'Overdue', 'due-today' => 'Due today'][$status] }}</p>
                                @endif
                                @if ($task->description)<p class="task-description text-secondary">{{ $task->description }}</p>@endif
                                <div class="task-controls d-flex justify-content-between gap-2 align-items-start mt-3">
                                    <form method="post" action="{{ route('tasks.toggle', $task) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="week" value="{{ $week->toDateString() }}">
                                        <input class="form-check-input completion-toggle" type="checkbox" @checked($task->is_completed) aria-label="{{ $task->is_completed ? 'Reopen' : 'Complete' }} {{ $task->title }}">
                                        <noscript><button class="btn btn-sm btn-outline-secondary">Toggle completion</button></noscript>
                                    </form>
                                    <div class="d-flex flex-column align-items-end gap-1">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#task-{{ $task->id }}" aria-label="Edit {{ $task->title }}">Edit</button>
                                        <form method="post" action="{{ route('tasks.destroy', $task) }}" class="delete-task" id="delete-task-{{ $task->id }}">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="week" value="{{ $week->toDateString() }}">
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-confirm" data-delete-form="delete-task-{{ $task->id }}" aria-label="Delete {{ $task->title }}">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <p class="empty-state">No tasks planned.</p>
                        @endforelse
                    </div>
                    @if ($loop->last)
                        <footer class="mt-auto p-3">
                            @if ($week->lt($window->lastWeek))
                                <a class="btn btn-outline-primary w-100" href="{{ route('tasks.index', ['week' => $week->addWeek()->toDateString()]) }}">Next Week &rarr;</a>
                            @else
                                <button class="btn btn-outline-primary w-100" disabled>Next Week &rarr;</button>
                            @endif
                        </footer>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
    <p class="small text-secondary mt-3">Planned hours include completed tasks. On smaller screens, scroll sideways to view the whole week.</p>
</main>
<div class="modal" id="delete-confirm" tabindex="-1" aria-labelledby="delete-confirm-title" aria-describedby="delete-confirm-description" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="delete-confirm-title">Delete task</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="delete-confirm-description">Delete this task? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="delete-cancel">Cancel</button>
                <button type="submit" class="btn btn-danger" id="delete-submit" disabled>Delete</button>
            </div>
        </div>
    </div>
</div>
@include('tasks.modal', ['task' => null])
@foreach ($modalTasks as $task)
    @include('tasks.modal', ['task' => $task])
@endforeach
</body>
</html>
