<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Country;
use App\Models\Organization;

class CountryOrganizationsList extends Component
{
    use WithPagination;

    public int $countryId;
    public string $search = '';
    public array $expandedUsages = [];

    // Reset pagination when search query changes
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggleUsage(int $orgId, string $type): void
    {
        $key = "{$orgId}-{$type}";
        if (isset($this->expandedUsages[$key])) {
            unset($this->expandedUsages[$key]);
        } else {
            $this->expandedUsages[$key] = true;
        }
    }

    public function getTeachersForUsage(int $orgId, string $type)
    {
        $org = Organization::find($orgId);
        if (!$org) return collect();

        return match ($type) {
            'educations' => \App\Models\Teacher::whereHas('educations', fn($q) => $q->where('educational_institution_id', $orgId))->get(),
            'jobExperiences' => \App\Models\Teacher::whereHas('jobExperiences', fn($q) => $q->where('organization_id', $orgId))->get(),
            'trainingExperiences' => \App\Models\Teacher::whereHas('trainingExperiences', fn($q) => $q->where('organization_id', $orgId))->get(),
            'memberships' => \App\Models\Teacher::whereHas('memberships', fn($q) => $q->where('membership_organization_id', $orgId))->get(),
            'awards' => \App\Models\Teacher::whereHas('awards', fn($q) => $q->where('awarding_body_organization_id', $orgId))->get(),
            'certifications' => \App\Models\Teacher::whereHas('certifications', fn($q) => $q->where('issuing_authority_organization_id', $orgId))->get(),
            'researchProjects' => \App\Models\Teacher::whereHas('researchProjects', fn($q) => $q->where('funding_agency_organization_id', $orgId))->get(),
            default => collect(),
        };
    }

    public function render()
    {
        $country = Country::findOrFail($this->countryId);

        $organizations = Organization::where('country_id', $this->countryId)
            ->when(trim($this->search) !== '', function ($query) {
                $query->where('name', 'like', '%' . trim($this->search) . '%');
            })
            ->orderBy('name')
            ->paginate(10)
            ->onEachSide(1); // Show 10 organizations per page, with 1 link on each side of the current page

        return view('livewire.country-organizations-list', [
            'country' => $country,
            'organizations' => $organizations,
        ]);
    }
}
