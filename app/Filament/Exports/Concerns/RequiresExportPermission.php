<?php

namespace App\Filament\Exports\Concerns;

/**
 * Who may download a company's data. Export is a separate right from viewing a list,
 * because a file leaves the system and can be shared or kept after access is removed.
 */
trait RequiresExportPermission
{
    /** Permission name that unlocks the export button; null leaves access to the resource's own rules. */
    protected static function exportPermission(): ?string
    {
        return null;
    }

    public static function canExport(): bool
    {
        $permission = static::exportPermission();
        $user = auth()->user();

        if ($permission === null) {
            return $user !== null;
        }

        return $user !== null && ($user->isSuperAdmin() || $user->can($permission));
    }
}
