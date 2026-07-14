<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\Department;
use Tests\TestCase;

class FrontendDepartmentsApiTest extends TestCase
{
    public function test_faculties_api_returns_title_and_total_count()
    {
        // Arrange
        Faculty::create([
            'name' => 'Faculty of Engineering ' . uniqid(),
            'short_name' => 'FE',
            'code' => 'FE_' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Act
        $response = $this->getJson('/api/v1/faculties');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'title',
            'total',
        ]);
        $this->assertEquals('Academic Faculties', $response->json('title'));
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    public function test_departments_api_returns_all_active_departments_sorted_by_sort_order_when_no_code_is_provided()
    {
        // Arrange
        $faculty = Faculty::create([
            'name' => 'Faculty of Engineering',
            'short_name' => 'FE',
            'code' => 'FE_' . uniqid(),
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $dept1 = Department::create([
            'name' => 'EEE',
            'code' => 'EEE_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $dept2 = Department::create([
            'name' => 'Civil',
            'code' => 'CE_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $inactiveDept = Department::create([
            'name' => 'Inactive Dept',
            'code' => 'ID_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        // Act
        $response = $this->getJson('/api/v1/departments');

        // Assert
        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();

        // Verify structure & active status
        $this->assertNotEmpty($data);
        
        $activeCodes = array_column($data, 'code');
        $this->assertContains($dept1->code, $activeCodes);
        $this->assertContains($dept2->code, $activeCodes);
        $this->assertNotContains($inactiveDept->code, $activeCodes);

        // Verify sorting by sort_order
        // Find indices of our created departments in the returned list
        $index1 = array_search($dept1->code, $activeCodes);
        $index2 = array_search($dept2->code, $activeCodes);
        
        // Since sort_order for CE (5) < EEE (10), CE must appear before EEE
        $this->assertTrue($index2 < $index1, "CE should be returned before EEE because of sort_order");
    }

    public function test_departments_api_filters_by_faculty_code()
    {
        // Arrange
        $faculty1 = Faculty::create([
            'name' => 'Faculty of Engineering',
            'short_name' => 'FE',
            'code' => 'FE_NEW_' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $faculty2 = Faculty::create([
            'name' => 'Faculty of Arts',
            'short_name' => 'FA',
            'code' => 'FA_NEW_' . uniqid(),
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $deptFE = Department::create([
            'name' => 'Computer Engineering',
            'code' => 'COE_' . uniqid(),
            'faculty_id' => $faculty1->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $deptFA = Department::create([
            'name' => 'English',
            'code' => 'ENG_' . uniqid(),
            'faculty_id' => $faculty2->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Act - Test with faculty_code query parameter
        $response1 = $this->getJson('/api/v1/departments?faculty_code=' . $faculty1->code);
        $response1->assertStatus(200);
        $data1 = $response1->json('data') ?? $response1->json();
        $codes1 = array_column($data1, 'code');
        
        $this->assertContains($deptFE->code, $codes1);
        $this->assertNotContains($deptFA->code, $codes1);

        // Act - Test with code query parameter (alternative)
        $response2 = $this->getJson('/api/v1/departments?code=' . $faculty1->code);
        $response2->assertStatus(200);
        $data2 = $response2->json('data') ?? $response2->json();
        $codes2 = array_column($data2, 'code');
        
        $this->assertContains($deptFE->code, $codes2);
        $this->assertNotContains($deptFA->code, $codes2);
    }

    public function test_faculties_code_route_filters_departments_by_faculty_code()
    {
        // Arrange
        $faculty = Faculty::create([
            'name' => 'Faculty of Business',
            'short_name' => 'FB',
            'code' => 'FB_NEW_' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $dept1 = Department::create([
            'name' => 'BBA',
            'code' => 'BBA_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $dept2 = Department::create([
            'name' => 'MBA',
            'code' => 'MBA_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Act
        $response = $this->getJson('/api/v1/faculties/' . $faculty->code);
        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();
        $codes = array_column($data, 'code');

        $this->assertContains($dept1->code, $codes);
        $this->assertContains($dept2->code, $codes);
    }

    public function test_administrative_roles_api_returns_roles_with_assigned_users_in_department_or_faculty()
    {
        // Arrange
        $faculty = Faculty::create([
            'name' => 'Faculty of Engineering',
            'short_name' => 'FE',
            'code' => 'FE_' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $dept = Department::create([
            'name' => 'CSE',
            'short_name' => 'CSE_SHORT_' . uniqid(),
            'code' => 'CSE_CODE_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user1 = \App\Models\User::create([
            'name' => 'User One',
            'email' => 'user1_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $user2 = \App\Models\User::create([
            'name' => 'User Two',
            'email' => 'user2_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $role1 = \App\Models\AdministrativeRole::create([
            'name' => 'Head of CSE',
            'short_name' => 'HEAD_CSE',
            'scope' => 'department',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $role2 = \App\Models\AdministrativeRole::create([
            'name' => 'Dean of FE',
            'short_name' => 'DEAN_FE',
            'scope' => 'faculty',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Unassigned role (should be removed from the list since it has no active assignments for this dept/faculty)
        $role3 = \App\Models\AdministrativeRole::create([
            'name' => 'Coordinator of BBA',
            'short_name' => 'CO_BBA',
            'scope' => 'department',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Attach user1 to role1 for this department
        $role1->users()->attach($user1->id, [
            'department_id' => $dept->id,
            'start_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        // Attach user2 to role2 for this faculty
        $role2->users()->attach($user2->id, [
            'faculty_id' => $faculty->id,
            'start_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        // Act
        $response = $this->getJson('/api/v1/administrative-roles/' . $dept->code);

        // Assert
        $response->assertStatus(200);
        $data = $response->json();

        $roleNames = array_column($data, 'name');
        $this->assertContains('Head of CSE', $roleNames);
        $this->assertContains('Dean of FE', $roleNames);
        $this->assertNotContains('Coordinator of BBA', $roleNames);
    }

    public function test_designations_api_returns_designations_with_active_teachers_in_department()
    {
        // Arrange
        $faculty = Faculty::create([
            'name' => 'Faculty of Science',
            'short_name' => 'FS',
            'code' => 'FS_' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $dept = Department::create([
            'name' => 'Physics',
            'short_name' => 'PHY_SHORT_' . uniqid(),
            'code' => 'PHY_CODE_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $desig1 = \App\Models\Designation::create([
            'name' => 'Professor',
            'short_name' => 'PROF',
            'rank' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $desig2 = \App\Models\Designation::create([
            'name' => 'Associate Professor',
            'short_name' => 'ASSOC_PROF',
            'rank' => 2,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Designations with no active teachers in this department
        $desig3 = \App\Models\Designation::create([
            'name' => 'Lecturer',
            'short_name' => 'LECT',
            'rank' => 3,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Create an other department for the primary department_id of teacher2
        $otherDept = Department::create([
            'name' => 'Chemistry',
            'short_name' => 'CHEM_' . uniqid(),
            'code' => 'CHEM_CODE_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Create teacher with desig1 (directly in department)
        \App\Models\Teacher::create([
            'first_name' => 'Alice',
            'last_name' => 'Physics',
            'secondary_email' => 'alice.physics.' . uniqid() . '@example.com',
            'department_id' => $dept->id,
            'designation_id' => $desig1->id,
            'webpage' => 'alice-phy-' . uniqid(),
            'is_active' => true,
            'is_archived' => false,
            'sort_order' => 1,
        ]);

        // Create teacher with desig2 (assigned via pivot table department_teacher)
        $teacher2 = \App\Models\Teacher::create([
            'first_name' => 'Bob',
            'last_name' => 'Physics',
            'secondary_email' => 'bob.physics.' . uniqid() . '@example.com',
            'department_id' => $otherDept->id,
            'designation_id' => $desig2->id,
            'webpage' => 'bob-phy-' . uniqid(),
            'is_active' => true,
            'is_archived' => false,
            'sort_order' => 2,
        ]);
        $teacher2->departments()->attach($dept->id, [
            'job_type_id' => 1,
            'sort_order' => 1,
        ]);

        // Act
        $response = $this->getJson('/api/v1/designation/' . $dept->code);

        // Assert
        $response->assertStatus(200);
        $data = $response->json();

        $desigNames = array_column($data, 'name');
        $this->assertContains('Professor', $desigNames);
        $this->assertContains('Associate Professor', $desigNames);
        $this->assertNotContains('Lecturer', $desigNames);

        $prof = collect($data)->firstWhere('name', 'Professor');
        $assocProf = collect($data)->firstWhere('name', 'Associate Professor');

        $this->assertNotNull($prof);
        $this->assertNotNull($assocProf);
        $this->assertEquals(1, $prof['teachers_count'] ?? 0);
        $this->assertEquals(1, $assocProf['teachers_count'] ?? 0);
    }

    public function test_department_teachers_api_returns_filtered_teachers_by_designation_or_administrative_role()
    {
        // Arrange
        $faculty = Faculty::create([
            'name' => 'Faculty of Engineering',
            'short_name' => 'FE',
            'code' => 'FE_' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $dept = Department::create([
            'name' => 'CSE',
            'short_name' => 'CSE_SHORT_' . uniqid(),
            'code' => 'CSE_CODE_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $desig1 = \App\Models\Designation::create([
            'name' => 'Professor',
            'short_name' => 'PROF',
            'rank' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $desig2 = \App\Models\Designation::create([
            'name' => 'Lecturer',
            'short_name' => 'LECT',
            'rank' => 2,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $role = \App\Models\AdministrativeRole::create([
            'name' => 'Head of CSE',
            'short_name' => 'HEAD_CSE',
            'scope' => 'department',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Create teacher 1 (Professor, also has Administrative Role)
        $teacher1 = \App\Models\Teacher::create([
            'first_name' => 'Alice',
            'last_name' => 'Professor',
            'secondary_email' => 'alice.prof.' . uniqid() . '@example.com',
            'department_id' => $dept->id,
            'designation_id' => $desig1->id,
            'webpage' => 'alice-prof-' . uniqid(),
            'is_active' => true,
            'is_archived' => false,
            'sort_order' => 1,
        ]);

        // Link teacher 1 to the administrative role
        $role->users()->attach($teacher1->user_id, [
            'department_id' => $dept->id,
            'start_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        // Create teacher 2 (Lecturer, no administrative role)
        $teacher2 = \App\Models\Teacher::create([
            'first_name' => 'Bob',
            'last_name' => 'Lecturer',
            'secondary_email' => 'bob.lect.' . uniqid() . '@example.com',
            'department_id' => $dept->id,
            'designation_id' => $desig2->id,
            'webpage' => 'bob-lect-' . uniqid(),
            'is_active' => true,
            'is_archived' => false,
            'sort_order' => 2,
        ]);

        // Act 1: Get all teachers of department
        $responseAll = $this->getJson("/api/v1/departments/{$dept->code}/teachers");
        $responseAll->assertStatus(200);
        $dataAll = $responseAll->json('data') ?? $responseAll->json();
        $namesAll = array_column($dataAll, 'name');
        $this->assertCount(2, $dataAll);
        $this->assertContains('Alice Professor', $namesAll);
        $this->assertContains('Bob Lecturer', $namesAll);

        // Act 2: Filter by designation_id
        $responseDesig = $this->getJson("/api/v1/departments/{$dept->code}/teachers?designation_id={$desig2->id}");
        $responseDesig->assertStatus(200);
        $dataDesig = $responseDesig->json('data') ?? $responseDesig->json();
        $namesDesig = array_column($dataDesig, 'name');
        $this->assertCount(1, $dataDesig);
        $this->assertContains('Bob Lecturer', $namesDesig);
        $this->assertNotContains('Alice Professor', $namesDesig);

        // Act 3: Filter by administrative_role_id
        $responseRole = $this->getJson("/api/v1/departments/{$dept->code}/teachers?administrative_role_id={$role->id}");
        $responseRole->assertStatus(200);
        $dataRole = $responseRole->json('data') ?? $responseRole->json();
        $namesRole = array_column($dataRole, 'name');
        $this->assertCount(1, $dataRole);
        $this->assertContains('Alice Professor', $namesRole);
        $this->assertNotContains('Bob Lecturer', $namesRole);
    }

    public function test_department_teachers_api_prioritizes_administrative_roles_in_sorting()
    {
        // Arrange
        $faculty = Faculty::create([
            'name' => 'Faculty of Engineering',
            'short_name' => 'FE',
            'code' => 'FE_' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $dept = Department::create([
            'name' => 'CSE',
            'short_name' => 'CSE_SHORT_' . uniqid(),
            'code' => 'CSE_CODE_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $desigProf = \App\Models\Designation::create([
            'name' => 'Professor',
            'short_name' => 'PROF',
            'rank' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $desigLect = \App\Models\Designation::create([
            'name' => 'Lecturer',
            'short_name' => 'LECT',
            'rank' => 2,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $role = \App\Models\AdministrativeRole::create([
            'name' => 'Coordinator',
            'short_name' => 'CO_COORD',
            'scope' => 'department',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $teacherProf = \App\Models\Teacher::create([
            'first_name' => 'Alice',
            'last_name' => 'Professor',
            'secondary_email' => 'alice.prof.' . uniqid() . '@example.com',
            'department_id' => $dept->id,
            'designation_id' => $desigProf->id,
            'webpage' => 'alice-prof-' . uniqid(),
            'is_active' => true,
            'is_archived' => false,
            'sort_order' => 1,
        ]);

        $teacherLect = \App\Models\Teacher::create([
            'first_name' => 'Bob',
            'last_name' => 'Lecturer',
            'secondary_email' => 'bob.lect.' . uniqid() . '@example.com',
            'department_id' => $dept->id,
            'designation_id' => $desigLect->id,
            'webpage' => 'bob-lect-' . uniqid(),
            'is_active' => true,
            'is_archived' => false,
            'sort_order' => 1,
        ]);

        $role->users()->attach($teacherLect->user_id, [
            'department_id' => $dept->id,
            'start_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        // Act: Get all teachers of department
        $response = $this->getJson("/api/v1/departments/{$dept->code}/teachers");
        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();

        // Assert: Lecturer (Bob) should be FIRST because of administrative role, and Professor (Alice) second
        $this->assertCount(2, $data);
        $this->assertEquals('Bob Lecturer', $data[0]['name']);
        $this->assertEquals('Alice Professor', $data[1]['name']);
    }

    public function test_teacher_profile_api_returns_teacher_details_using_webpage_and_department()
    {
        // Arrange
        $faculty = Faculty::create([
            'name' => 'Faculty of Engineering',
            'short_name' => 'FE',
            'code' => 'FE_' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $dept = Department::create([
            'name' => 'CSE',
            'short_name' => 'CSE_SHORT_' . uniqid(),
            'code' => 'CSE_CODE_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $desig = \App\Models\Designation::create([
            'name' => 'Professor',
            'short_name' => 'PROF',
            'rank' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $teacher = \App\Models\Teacher::create([
            'first_name' => 'Alice',
            'last_name' => 'Professor',
            'secondary_email' => 'alice.profile.' . uniqid() . '@example.com',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'webpage' => 'alice-profile-' . uniqid(),
            'is_active' => true,
            'is_archived' => false,
            'sort_order' => 1,
        ]);

        // Act & Assert 1: Valid department and webpage
        $response = $this->getJson("/api/v1/departments/{$dept->code}/teachers/{$teacher->webpage}");
        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();
        $this->assertEquals('Alice Professor', $data['name']);

        // Act & Assert 2: Invalid webpage
        $responseInvalidWebpage = $this->getJson("/api/v1/departments/{$dept->code}/teachers/invalid-webpage");
        $responseInvalidWebpage->assertStatus(404);

        // Act & Assert 3: Unrelated department
        $unrelatedDept = Department::create([
            'name' => 'Civil',
            'short_name' => 'CE_SHORT_' . uniqid(),
            'code' => 'CE_CODE_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $responseUnrelatedDept = $this->getJson("/api/v1/departments/{$unrelatedDept->code}/teachers/{$teacher->webpage}");
        $responseUnrelatedDept->assertStatus(404);
    }

    public function test_faculty_teachers_api_returns_all_teachers_sorted_by_designation()
    {
        // Arrange: create faculty, 2 departments, designations and teachers
        $faculty = \App\Models\Faculty::create([
            'name'       => 'Faculty of Test ' . uniqid(),
            'short_name' => 'FT' . uniqid(),
            'code'       => 'FT_' . uniqid(),
            'is_active'  => true,
            'sort_order' => 99,
        ]);

        $dept1 = \App\Models\Department::create([
            'name'       => 'Dept Alpha',
            'short_name' => 'ALPHA',
            'code'       => 'ALPHA_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        $dept2 = \App\Models\Department::create([
            'name'       => 'Dept Beta',
            'short_name' => 'BETA',
            'code'       => 'BETA_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active'  => true,
            'sort_order' => 2,
        ]);

        $professorDesig = \App\Models\Designation::create([
            'name' => 'Professor ' . uniqid(), 'sort_order' => 1,
        ]);
        $lectureDesig = \App\Models\Designation::create([
            'name' => 'Lecturer ' . uniqid(), 'sort_order' => 5,
        ]);

        $user1 = \App\Models\User::factory()->create();
        $professor = \App\Models\Teacher::create([
            'first_name'     => 'Prof',
            'last_name'      => 'Alpha',
            'email'          => 'prof.alpha.' . uniqid() . '@diu.edu',
            'webpage'        => 'prof-alpha-' . uniqid(),
            'department_id'  => $dept1->id,
            'designation_id' => $professorDesig->id,
            'is_active'      => true,
            'is_archived'    => false,
            'user_id'        => $user1->id,
        ]);

        $user2 = \App\Models\User::factory()->create();
        $lecturer = \App\Models\Teacher::create([
            'first_name'     => 'Lec',
            'last_name'      => 'Beta',
            'email'          => 'lec.beta.' . uniqid() . '@diu.edu',
            'webpage'        => 'lec-beta-' . uniqid(),
            'department_id'  => $dept2->id,
            'designation_id' => $lectureDesig->id,
            'is_active'      => true,
            'is_archived'    => false,
            'user_id'        => $user2->id,
        ]);

        // Act
        $response = $this->getJson("/api/v1/faculties/{$faculty->code}/teachers");

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [['id', 'name', 'designation', 'designation_sort_order']],
            'title',
            'faculty',
            'total',
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Professor (sort_order=1) should come before Lecturer (sort_order=5)
        $this->assertEquals($professor->id, $data[0]['id']);
        $this->assertEquals($lecturer->id, $data[1]['id']);
        $this->assertEquals($faculty->code, $response->json('faculty'));
        $this->assertEquals(2, $response->json('total'));
    }

    public function test_teachers_count_returns_correct_number()
    {
        // Arrange
        \App\Models\Teacher::query()->delete(); // Clear existing

        $faculty = \App\Models\Faculty::create([
            'name'       => 'Faculty of Test ' . uniqid(),
            'short_name' => 'FT' . uniqid(),
            'code'       => 'FT_' . uniqid(),
            'is_active'  => true,
            'sort_order' => 99,
        ]);

        $professorDesig = \App\Models\Designation::create(['name' => 'Professor', 'sort_order' => 1]);

        $dept = \App\Models\Department::create([
            'name'       => 'Dept Alpha',
            'short_name' => 'ALPHA',
            'code'       => 'ALPHA_' . uniqid(),
            'faculty_id' => $faculty->id,
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        // 3 active teachers
        for ($i = 0; $i < 3; $i++) {
            $user = \App\Models\User::factory()->create();
            \App\Models\Teacher::create([
                'first_name'     => 'Active' . $i,
                'last_name'      => 'Teacher',
                'email'          => "active.teacher.{$i}." . uniqid() . "@diu.edu",
                'webpage'        => "active-teacher-{$i}-" . uniqid(),
                'department_id'  => $dept->id,
                'designation_id' => $professorDesig->id,
                'is_active'      => true,
                'is_archived'    => false,
                'user_id'        => $user->id,
            ]);
        }

        // 1 inactive teacher
        $userInactive = \App\Models\User::factory()->create();
        \App\Models\Teacher::create([
            'first_name'     => 'Inactive',
            'last_name'      => 'Teacher',
            'email'          => "inactive.teacher." . uniqid() . "@diu.edu",
            'webpage'        => "inactive-teacher-" . uniqid(),
            'department_id'  => $dept->id,
            'designation_id' => $professorDesig->id,
            'is_active'      => false,
            'is_archived'    => false,
            'user_id'        => $userInactive->id,
        ]);

        // 1 archived teacher
        $userArchived = \App\Models\User::factory()->create();
        \App\Models\Teacher::create([
            'first_name'     => 'Archived',
            'last_name'      => 'Teacher',
            'email'          => "archived.teacher." . uniqid() . "@diu.edu",
            'webpage'        => "archived-teacher-" . uniqid(),
            'department_id'  => $dept->id,
            'designation_id' => $professorDesig->id,
            'is_active'      => true,
            'is_archived'    => true,
            'user_id'        => $userArchived->id,
        ]);

        // Act
        $response = $this->getJson('/api/v1/teachers/count');

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'count' => 3,
        ]);
    }
}
