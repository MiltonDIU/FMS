<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Faculty;
use App\Models\Setting;
use App\Models\Teacher;
use Tests\TestCase;

/**
 * Covers the public Blade + Livewire frontend routes.
 *
 * Replaces FrontendDriverMiddlewareTest, which also exercised the removed
 * Next.js redirect middleware and the /api/v1/settings endpoint that existed
 * only to configure that frontend.
 */
class FrontendRoutesTest extends TestCase
{
    protected $initialActiveTheme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialActiveTheme = Setting::get('active_theme', 'theme_default');
        Setting::set('active_theme', 'theme_default');

        // Seed testing entities
        $faculty = Faculty::updateOrCreate(['short_name' => 'FSIT'], [
            'name' => 'Faculty of Science & Information Technology',
            'code' => 'FSIT',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $department = Department::updateOrCreate(['code' => 'CSE'], [
            'name' => 'Computer Science & Engineering',
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $designation = Designation::updateOrCreate(['name' => 'Test Designation'], [
            'sort_order' => -9999,
        ]);

        Teacher::updateOrCreate(['webpage' => 'faculty-teacher'], [
            'first_name' => 'Test',
            'last_name' => 'Teacher',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'is_active' => true,
            'is_archived' => false,
            'login_allowed' => true,
            'sort_order' => -9999,
        ]);
    }

    protected function tearDown(): void
    {
        Setting::set('active_theme', $this->initialActiveTheme);

        parent::tearDown();
    }

    public function test_loads_home_page()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Faculty of Science &amp; Information Technology', false);
    }

    public function test_loads_faculty_nested_route()
    {
        $response = $this->get('/fsit');

        $response->assertStatus(200);
        $response->assertSee('Computer Science &amp; Engineering', false);
    }

    public function test_loads_department_nested_route()
    {
        $response = $this->get('/fsit/cse');

        $response->assertStatus(200);
        $response->assertSee('Test  Teacher');
    }

    public function test_loads_teacher_profile_nested_route()
    {
        $response = $this->get('/fsit/cse/faculty-teacher');

        $response->assertStatus(200);
        $response->assertSee('Test  Teacher');
    }
}
