<?php

namespace App\Services;

use App\Models\{Project, User};
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Collection;

/**
 * The one way workflow events reach people. A failed notification must never undo
 * the business action that caused it, so errors are reported, not thrown.
 */
class WorkflowNotifier
{
    /**
     * @param  iterable<User|null>  $recipients
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     */
    public function send(iterable $recipients, string $event, array $params = [], ?string $url = null, ?int $projectId = null, array $context = []): int
    {
        $sent = 0;

        foreach (collect($recipients)->filter()->unique('id') as $user) {
            try {
                $user->notify(new WorkflowNotification($event, $params, $url, $projectId, $context));
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $sent;
    }

    /** The project's active members, plus the person who acted. */
    public function projectTeam(?Project $project, ?User $actor = null, bool $includeActor = true): Collection
    {
        $members = $project
            ? $project->activeMembers()->with('user')->get()->pluck('user')->filter()
            : collect();

        return ($includeActor && $actor ? $members->push($actor) : $members)->unique('id')->values();
    }

    /**
     * Everyone in the company who holds one of these permissions: the people who must act
     * (approvers, reviewers). The person who triggered the event is left out.
     *
     * @param  string|string[]  $permissions
     */
    public function withPermission(int $companyId, string|array $permissions, ?User $except = null): Collection
    {
        return User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (User $user): bool => ($except === null || $user->id !== $except->id)
                && collect((array) $permissions)->contains(fn (string $permission): bool => $user->can($permission)))
            ->values();
    }

    /** Resolves ids to users of one company, leaving out the actor and anyone from another company. */
    public function users(int $companyId, iterable $ids, ?User $except = null): Collection
    {
        return User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('id', collect($ids)->filter()->unique()->all())
            ->get()
            ->reject(fn (User $user): bool => $except !== null && $user->id === $except->id)
            ->values();
    }
}
