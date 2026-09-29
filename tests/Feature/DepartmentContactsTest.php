<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Services\DepartmentContacts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Dean / Head / office contacts come from a server this project does not
 * own, and they are now rendered on the department listing — a page that gets
 * far more traffic than the contact page ever did.
 *
 * So the two properties worth guarding are not about formatting: an answer must
 * be reused rather than re-fetched, and an outage must not be re-dialled on
 * every page view either.
 */
class DepartmentContactsTest extends TestCase
{
    protected function department(string $code = 'cse'): Department
    {
        $department = Department::where('code', $code)->first();

        if (! $department) {
            $this->markTestSkipped("No department with code {$code} in the development database.");
        }

        Cache::forget("department-contacts.{$code}");

        return $department;
    }

    protected function payload(): array
    {
        return [
            'data' => [
                'department' => ['department_name' => 'Computer Science', 'faculty_name' => 'FSIT'],
                'deans' => [
                    ['name' => 'A Dean', 'email' => 'dean@example.test', 'designation' => 'Dean', 'photo' => '/uploads/dean.jpg'],
                ],
                'department_heads' => [
                    // No name — the API pads its lists, and a nameless row is not a person.
                    ['email' => 'ghost@example.test'],
                    ['name' => 'A Head', 'mobile' => '01700000000', 'ip_phone' => '5555'],
                ],
            ],
        ];
    }

    public function test_a_good_answer_is_normalised_into_blocks(): void
    {
        Http::fake(['*' => Http::response($this->payload(), 200)]);

        $contacts = DepartmentContacts::for($this->department());

        $this->assertNull($contacts['error']);
        $this->assertFalse(DepartmentContacts::isEmpty($contacts));

        $this->assertCount(1, $contacts['sections']['deans']);
        $this->assertSame('A Dean', $contacts['sections']['deans'][0]['name']);
        $this->assertSame(
            DepartmentContacts::PHOTO_BASE_URL . 'uploads/dean.jpg',
            $contacts['sections']['deans'][0]['photo_url'],
        );

        // The nameless row is dropped, and the survivor is re-indexed from zero
        // so the views can loop over a plain list.
        $this->assertCount(1, $contacts['sections']['department_heads']);
        $this->assertSame('A Head', $contacts['sections']['department_heads'][0]['name']);

        // Groups the API omitted come back empty rather than missing.
        $this->assertSame([], $contacts['sections']['deans_officers']);
    }

    public function test_a_second_lookup_does_not_call_the_api_again(): void
    {
        Http::fake(['*' => Http::response($this->payload(), 200)]);

        $department = $this->department();

        DepartmentContacts::for($department);
        DepartmentContacts::for($department);

        Http::assertSentCount(1);
    }

    public function test_a_failure_is_reported_and_also_cached(): void
    {
        Http::fake(['*' => Http::response('nope', 503)]);

        $department = $this->department();

        $contacts = DepartmentContacts::for($department);

        $this->assertStringContainsString('503', (string) $contacts['error']);
        $this->assertTrue(DepartmentContacts::isEmpty($contacts));

        // A dead API costs one slow request per FAILURE_TTL, not one per visitor.
        DepartmentContacts::for($department);
        Http::assertSentCount(1);
    }

    public function test_a_department_without_a_code_is_never_looked_up(): void
    {
        Http::fake();

        $contacts = DepartmentContacts::for(new Department(['code' => null]));

        $this->assertNotNull($contacts['error']);
        $this->assertTrue(DepartmentContacts::isEmpty($contacts));

        Http::assertNothingSent();
    }
}
