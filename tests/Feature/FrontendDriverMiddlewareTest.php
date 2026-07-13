<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Teacher;
use Tests\TestCase;

class FrontendDriverMiddlewareTest extends TestCase
{
    protected $initialDriver;
    protected $initialNextjsUrl;
    protected $initialActiveTheme;

    protected function setUp(): void
    {
        parent::setUp();

        // Backup current settings
        $this->initialDriver = Setting::get('frontend_driver', 'blade');
        $this->initialNextjsUrl = Setting::get('nextjs_url', '');
        $this->initialActiveTheme = Setting::get('active_theme', 'theme_default');

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
        
        $designation = Designation::firstOrCreate(['name' => 'Lecturer'], [
            'sort_order' => 1,
        ]);

        Teacher::updateOrCreate(['webpage' => 'faculty-teacher'], [
            'first_name' => 'Test',
            'last_name' => 'Teacher',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'is_active' => true,
            'is_archived' => false,
            'login_allowed' => true,
            'sort_order' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        // Restore settings
        Setting::set('frontend_driver', $this->initialDriver);
        Setting::set('nextjs_url', $this->initialNextjsUrl);
        Setting::set('active_theme', $this->initialActiveTheme);

        parent::tearDown();
    }

    public function test_loads_blade_frontend_by_default()
    {
        Setting::set('frontend_driver', 'blade');
        Setting::set('active_theme', 'theme_default');

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Welcome to the');
    }

    public function test_loads_faculty_nested_route()
    {
        Setting::set('frontend_driver', 'blade');
        Setting::set('active_theme', 'theme_default');

        $response = $this->get('/fsit');
        $response->assertStatus(200);
        $response->assertSee('Computer Science & Engineering');
    }

    public function test_loads_department_nested_route()
    {
        Setting::set('frontend_driver', 'blade');
        Setting::set('active_theme', 'theme_default');

        $response = $this->get('/fsit/cse');
        $response->assertStatus(200);
        $response->assertSee('Test  Teacher');
    }

    public function test_loads_teacher_profile_nested_route()
    {
        Setting::set('frontend_driver', 'blade');
        Setting::set('active_theme', 'theme_default');

        $response = $this->get('/fsit/cse/faculty-teacher');
        $response->assertStatus(200);
        $response->assertSee('Test  Teacher');
    }

    public function test_redirects_to_nextjs_url_when_nextjs_driver_active()
    {
        Setting::set('frontend_driver', 'nextjs');
        Setting::set('nextjs_url', 'https://teachers.diu.edu.bd');

        $response = $this->get('/fsit/cse/faculty-teacher');

        $response->assertRedirect('https://teachers.diu.edu.bd/fsit/cse/faculty-teacher');
    }

    public function test_settings_api_endpoint()
    {
        Setting::set('frontend_driver', 'nextjs');
        Setting::set('nextjs_url', 'https://teachers.diu.edu.bd');
        Setting::set('active_theme', 'theme_modern');

        $response = $this->getJson('/api/v1/settings');

        $response->assertStatus(200);
        $response->assertJson([
            'frontend_driver' => 'nextjs',
            'nextjs_url' => 'https://teachers.diu.edu.bd',
            'active_theme' => 'theme_modern',
        ]);
    }
}
