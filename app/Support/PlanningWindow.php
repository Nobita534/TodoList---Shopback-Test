<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PlanningWindow
{
    public readonly CarbonImmutable $today;

    public readonly CarbonImmutable $end;

    public readonly CarbonImmutable $firstWeek;

    public readonly CarbonImmutable $lastWeek;

    public function __construct()
    {
        $this->today = CarbonImmutable::today('Asia/Ho_Chi_Minh');
        $this->end = $this->today->addMonthNoOverflow();
        $this->firstWeek = $this->today->startOfWeek(CarbonInterface::MONDAY);
        $this->lastWeek = $this->end->startOfWeek(CarbonInterface::MONDAY);
    }

    public function contains(CarbonInterface $date): bool
    {
        return $date->betweenIncluded($this->today, $this->end);
    }

    public function resolveWeek(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return $this->firstWeek;
        }

        // Compare only against the permitted Mondays: malformed and impossible dates fail too.
        for ($week = $this->firstWeek; $week->lte($this->lastWeek); $week = $week->addWeek()) {
            if ($value === $week->toDateString()) {
                return $week;
            }
        }

        return null;
    }
}
