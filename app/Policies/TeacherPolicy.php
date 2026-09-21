<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Teacher;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TeacherPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     * Super Admin role automatically bypasses all permission checks.
     */
    public function before(AuthUser $authUser, string $ability): ?bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Teacher');
    }

    public function view(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('View:Teacher');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Teacher');
    }

    public function update(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('Update:Teacher');
    }

    public function delete(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('Delete:Teacher');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Teacher');
    }

    public function restore(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('Restore:Teacher');
    }

    public function forceDelete(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('ForceDelete:Teacher');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Teacher');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Teacher');
    }

    public function replicate(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('Replicate:Teacher');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Teacher');
    }

    /**
     * Custom Policy Abilities for Teacher Resource actions (Strict 1-to-1 permission checks)
     */
    public function batchCalculateProfileScores(AuthUser $authUser): bool
    {
        return $authUser->can('BatchCalculateProfileScores:Teacher');
    }

    public function syncProfileScore(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('SyncProfileScore:Teacher');
    }

    public function bulkSendEmailToTeachers(AuthUser $authUser): bool
    {
        return $authUser->can('BulkSendEmailToTeachers:Teacher');
    }

    public function sendTeacherEmail(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('SendTeacherEmail:Teacher');
    }

    public function importErp(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('ImportErp:Teacher');
    }

    /*
     * Filling profile fields from the ERP is split in two on purpose. Refreshing
     * the teacher in front of you is an everyday correction; starting a run
     * across an employment status touches hundreds of records at once and
     * writes past the approval workflow, so it is a different thing to be
     * trusted with.
     */
    public function syncErpProfile(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('SyncErpProfile:Teacher');
    }

    public function bulkSyncErpProfiles(AuthUser $authUser): bool
    {
        return $authUser->can('BulkSyncErpProfiles:Teacher');
    }

    public function viewDashboard(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('ViewDashboard:Teacher');
    }

    public function toggleLoginAllowed(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('ToggleLoginAllowed:Teacher');
    }

    /**
     * Who may put a teacher into the research directory, or take them out.
     *
     * Separate from Update:Teacher because it is not an edit to the profile —
     * it decides whether the profile is published to the research site through
     * the API, which is a different audience from this system. The research
     * team keeps that list, so the permission goes to them rather than to
     * everyone who can correct a phone number.
     */
    public function toggleResearcher(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('ToggleResearcher:Teacher');
    }

    /**
     * Who may take a teacher's photograph out of the system as a file.
     *
     * Held apart from View:Teacher and Update:Teacher. Reading a profile is not
     * the same as walking away with the photograph on it: the file cannot be
     * recalled once it has been saved, so it is worth granting on purpose
     * rather than inheriting from the ability to open the page.
     *
     * Only super_admin holds it today, and super_admin reaches it through
     * before() regardless. It is an ordinary ability with an ordinary
     * permission behind it, so any other role can be given
     * DownloadPhoto:Teacher from the roles screen whenever that is wanted,
     * with no code change.
     */
    public function downloadPhoto(AuthUser $authUser, ?Teacher $teacher = null): bool
    {
        return $authUser->can('DownloadPhoto:Teacher');
    }
}