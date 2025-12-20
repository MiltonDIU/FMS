# Teacher Profile Management System - Implementation Plan

## User Clarifications ✅
- **Fields**: Use only fields from our existing tables (not full ERP)
- **Media**: Use Spatie Media Library for images/files
- **Teacher Navigation**: Teacher role → clicks "Profile" → opens own profile directly
- **Admin Navigation**: Admin → sees teacher list → clicks edit → opens same form

---

## Spatie Media Library Integration

### Installation
```bash
composer require filament/spatie-laravel-media-library-plugin:"^4.0" -W
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

### Model Setup
```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Teacher extends Model implements HasMedia
{
    use InteractsWithMedia;
    
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->useFallbackUrl('/images/default-avatar.png');
            
        $this->addMediaCollection('documents');
    }
}
```

### Form Usage
```php
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

SpatieMediaLibraryFileUpload::make('avatar')
    ->collection('avatar')
    ->image()
    ->avatar();
```

---

## Navigation Flow

### Teacher Role
```
Dashboard → "My Profile" (sidebar) → Own Profile Form
```
- Clicks "My Profile" in sidebar
- Directly opens their own profile (no list view)
- Can edit and save as Draft
- Can submit for review

### Admin/Registrar/HR
```
Dashboard → "Teachers" (sidebar) → List View → Click Edit → Profile Form
```
- Sees list of all teachers
- Can filter, search, sort
- Clicks Edit → Opens same profile form
- Can edit and Direct Publish

---

## Access Control & Permissions

| Role | View | Edit Own | Edit Others | Direct Publish |
|------|------|----------|-------------|----------------|
| Teacher | Own only | ✅ Draft | ❌ | ❌ |
| Admin/Registrar/HR | All | ✅ | ✅ | ✅ |

---

## Profile Sections (From Our Tables)

### 1. Basic Information (teachers table)
- Profile Photo (via Media Library)
- employee_id, first_name, middle_name, last_name
- department_id, designation_id
- phone, personal_phone, secondary_email

### 2. Personal Information (teachers table)
- date_of_birth, gender, blood_group
- nationality, religion
- present_address, permanent_address

### 3. Employment (teachers table)
- joining_date, work_location, office_room
- bio, research_interest, personal_website
- google_scholar, research_gate, orcid

### 4. Education (educations table)
- level, degree, field_of_study, institution
- country, passing_year, cgpa, thesis_title

### 5. Publications (publications table)
- type, title, authors, journal_name
- doi, publication_year, indexed_by

### 6. Research (research_projects table)
- title, funding_agency, budget, role, dates

### 7. Experience (job_experiences table)
- position, organization, dates, responsibilities

### 8. Skills & Certifications
- skills table: category, name, proficiency
- certifications table: title, issuer, dates
- training_experiences table

### 9. Teaching & Memberships
- teaching_areas table
- memberships table
- awards table

### 10. Social Links (social_links table)
- platform, username, url

---

## Implementation Steps

### Phase 1: Setup ✅
- [ ] Install Spatie Media Library
- [ ] Update Teacher model with HasMedia
- [ ] Run migrations

### Phase 2: TeacherResource
- [ ] Create TeacherResource with list/edit/view
- [ ] Add profile sections as Tabs
- [ ] Add relation managers for each section

### Phase 3: Teacher Self-Service
- [ ] Create "My Profile" page for teachers
- [ ] Redirect teachers to own profile
- [ ] Draft save functionality

### Phase 4: Version Control
- [ ] Draft/Published toggle
- [ ] Version history panel
- [ ] Comparison view

### Phase 5: Approval Workflow
- [ ] Submit for review action
- [ ] Approve/Reject for admins
- [ ] Review remarks

---

## Files to Create

| File | Purpose |
|------|---------|
| `TeacherResource.php` | Main resource |
| `TeacherPolicy.php` | Authorization |
| `EditTeacherProfile.php` | Teacher's own profile page |
| `EducationRelationManager.php` | Education section |
| `PublicationRelationManager.php` | Publications |
| ... other relation managers | Other sections |

