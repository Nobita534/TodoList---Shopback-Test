<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'title', 'description', 'estimated_hours', 'priority',
        'scheduled_date', 'due_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_hours' => 'decimal:1',
            'scheduled_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'is_completed' => 'boolean',
        ];
    }

    // SQLite does not coerce DATE columns; store date-only values rather than timestamps.
    protected function scheduledDate(): Attribute
    {
        return Attribute::make(set: fn ($value) => CarbonImmutable::parse($value)->toDateString());
    }

    protected function dueDate(): Attribute
    {
        return Attribute::make(set: fn ($value) => CarbonImmutable::parse($value)->toDateString());
    }

    public function statusOn(CarbonInterface $today): string
    {
        if ($this->is_completed) {
            return 'completed';
        }

        if ($this->due_date->lt($today)) {
            return 'overdue';
        }

        return $this->due_date->isSameDay($today) ? 'due-today' : 'planned';
    }
}
