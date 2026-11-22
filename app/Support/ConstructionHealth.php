<?php

namespace App\Support;

use App\Models\{ConstructionWorkPackage, ProjectConstruction};

/** One place that decides how a construction stage is doing, so every screen says the same thing. */
class ConstructionHealth
{
    /** Pass a package loaded with `delays_count` and `serious_open_issues_count`. */
    public static function forPackage(ConstructionWorkPackage $package): string
    {
        if ($package->status === 'completed') {
            return 'done';
        }
        if ($package->status === 'cancelled') {
            return 'cancelled';
        }
        if ($package->planned_end_date && $package->planned_end_date->isPast() && ! $package->planned_end_date->isToday()) {
            return 'overdue';
        }
        if (($package->delays_count ?? 0) > 0 || ($package->serious_open_issues_count ?? 0) > 0) {
            return 'attention';
        }

        return 'on_track';
    }

    public static function color(string $health): string
    {
        return match ($health) {
            'overdue' => 'danger',
            'attention' => 'warning',
            'on_track' => 'success',
            default => 'gray',
        };
    }

    /** The next step for the whole construction, in one sentence. */
    public static function nextStep(ProjectConstruction $construction): string
    {
        $packages = $construction->workPackages()
            ->withCount(['delays', 'issues as serious_open_issues_count' => fn ($query) => $query->whereIn('severity', ['high', 'critical'])->whereNotIn('status', ['resolved', 'closed'])])
            ->get();
        $live = $packages->reject(fn (ConstructionWorkPackage $package): bool => $package->status === 'cancelled');

        return match (true) {
            $construction->status === 'completed' => __('project.construction.next.completed'),
            $construction->status === 'cancelled' => __('project.construction.next.cancelled'),
            $construction->status === 'planned' && $live->isEmpty() => __('project.construction.next.planned_empty'),
            $construction->status === 'planned' => __('project.construction.next.planned_ready'),
            $live->isEmpty() => __('project.construction.next.running_empty'),
            $live->every(fn (ConstructionWorkPackage $package): bool => $package->status === 'completed') => __('project.construction.next.all_done'),
            ($overdue = $live->filter(fn (ConstructionWorkPackage $package): bool => self::forPackage($package) === 'overdue')->count()) > 0 => __('project.construction.next.running_overdue', ['progress' => (int) $construction->progress_percentage, 'overdue' => $overdue]),
            default => __('project.construction.next.running', [
                'progress' => (int) $construction->progress_percentage,
                'active' => $live->where('status', 'in_progress')->count(),
                'done' => $live->where('status', 'completed')->count(),
                'total' => $live->count(),
            ]),
        };
    }
}
