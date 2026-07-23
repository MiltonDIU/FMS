<?php

namespace App\Observers;

use App\Models\UserAdministrativeRole;
use Spatie\Permission\Models\Role;

/**
 * UserAdministrativeRoleObserver
 *
 * Automatically syncs Spatie roles when a user is assigned / updated / removed
 * from an administrative role.
 *
 * Administrative Role → Spatie role mapping (by role NAME + scope):
 *
 *  Faculty-scoped roles:
 *   - Dean                → 'dean'
 *   - Associate Dean      → 'associate_dean'
 *   - Dean officer        → 'associate_dean'
 *
 *  Department-scoped roles:
 *   - Head of Department  → 'head'
 *   - Associate Head      → 'associate_head'
 *   - Head Officer        → 'associate_head'
 *
 *  University / Program scoped → no Spatie role change
 *
 * Rules:
 *  1. On create / activate  → assign the matching Spatie role (if not already present)
 *  2. On update             → if deactivated / end_date set → remove role if no other active assignment
 *  3. On delete (soft)      → same cleanup as deactivation
 *  4. On restore            → re-assign if still active
 */
class UserAdministrativeRoleObserver
{
    /**
     * Mapping: administrative role NAME → Spatie role name.
     * Lower-cased names for case-insensitive matching.
     */
    private const ROLE_MAP = [
        // Faculty scope
        'dean'                => 'dean',
        'associate dean'      => 'associate_dean',
        'dean officer'        => 'associate_dean',

        // Department scope
        'head of department'  => 'head',
        'associate head'      => 'associate_head',
        'head officer'        => 'associate_head',
    ];

    /**
     * Handle the UserAdministrativeRole "created" event.
     */
    public function created(UserAdministrativeRole $record): void
    {
        if ($record->is_active && ! $record->end_date) {
            $this->assignSpatieRole($record);
        }
    }

    /**
     * Handle the UserAdministrativeRole "updated" event.
     */
    public function updated(UserAdministrativeRole $record): void
    {
        $wasActive  = $record->getOriginal('is_active');
        $isNowActive = $record->is_active;
        $hadEndDate = $record->getOriginal('end_date');
        $hasEndDate = $record->end_date;

        // Activated or end_date cleared: assign role
        if ($isNowActive && ! $hasEndDate) {
            $this->assignSpatieRole($record);
        }

        // Deactivated or end_date set: maybe remove role
        if ((! $isNowActive && $wasActive) || (! $hadEndDate && $hasEndDate)) {
            $this->removeSpatieRoleIfOrphaned($record);
        }
    }

    /**
     * Handle the UserAdministrativeRole "deleted" (soft delete) event.
     */
    public function deleted(UserAdministrativeRole $record): void
    {
        $this->removeSpatieRoleIfOrphaned($record);
    }

    /**
     * Handle the UserAdministrativeRole "restored" event.
     */
    public function restored(UserAdministrativeRole $record): void
    {
        if ($record->is_active && ! $record->end_date) {
            $this->assignSpatieRole($record);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Resolve the Spatie role name for a given UserAdministrativeRole record.
     * Matches by administrative role NAME (case-insensitive).
     */
    private function resolveSpatiRole(UserAdministrativeRole $record): ?string
    {
        $adminRoleName = strtolower(optional($record->administrativeRole)->name ?? '');

        return self::ROLE_MAP[$adminRoleName] ?? null;
    }

    /**
     * Assign the matching Spatie role to the user (if not already present).
     */
    private function assignSpatieRole(UserAdministrativeRole $record): void
    {
        $spatieRoleName = $this->resolveSpatiRole($record);

        if (! $spatieRoleName) {
            return;
        }

        $user = $record->user;

        if (! $user) {
            return;
        }

        $role = Role::firstOrCreate(['name' => $spatieRoleName, 'guard_name' => 'web']);

        if (! $user->hasRole($spatieRoleName)) {
            $user->assignRole($role);
        }
    }

    /**
     * Remove the matching Spatie role only if the user has NO other active
     * administrative role assignments that map to the same Spatie role.
     */
    private function removeSpatieRoleIfOrphaned(UserAdministrativeRole $record): void
    {
        $spatieRoleName = $this->resolveSpatiRole($record);

        if (! $spatieRoleName) {
            return;
        }

        $user = $record->user;

        if (! $user) {
            return;
        }

        // Find all administrative role names that map to the same Spatie role
        $adminRoleNames = array_keys(
            array_filter(self::ROLE_MAP, fn($v) => $v === $spatieRoleName)
        );

        // Count remaining OTHER active assignments that also map to the same Spatie role
        $remainingActiveAssignments = UserAdministrativeRole::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $record->id)
            ->where('is_active', true)
            ->whereNull('end_date')
            ->whereNull('deleted_at')
            ->whereHas('administrativeRole', fn ($q) => $q->whereIn(
                \DB::raw('LOWER(name)'),
                $adminRoleNames
            ))
            ->count();

        // Only remove Spatie role if no other active assignments justify keeping it
        if ($remainingActiveAssignments === 0 && $user->hasRole($spatieRoleName)) {
            $user->removeRole($spatieRoleName);
        }
    }
}
