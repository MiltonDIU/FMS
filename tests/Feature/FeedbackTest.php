<?php

namespace Tests\Feature;

use App\Filament\Resources\Feedback\FeedbackResource;
use App\Filament\Resources\Feedback\Pages\ViewFeedback;
use App\Filament\Resources\MyFeedback\MyFeedbackResource;
use App\Filament\Resources\MyFeedback\Pages\CreateMyFeedback;
use App\Filament\Resources\MyFeedback\Pages\ListMyFeedback;
use App\Filament\Resources\MyFeedback\Pages\ViewMyFeedback;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Feedback;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\FeedbackReceived;
use App\Notifications\FeedbackUpdated;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The round trip the feature exists for: a teacher reports something, the team
 * answers and marks it done, and the teacher confirms it or sends it back.
 */
class FeedbackTest extends TestCase
{
    protected User $teacherUser;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Setting::set(Feedback::ENABLED_SETTING, true);
        Setting::set(Feedback::EMAIL_UPDATES_SETTING, true);

        $this->teacherUser = $this->makeTeacher()->user;

        $this->admin = User::forceCreate([
            'name' => 'Feedback Admin',
            'email' => 'feedback-admin-' . Str::random(10) . '@example.test',
            'password' => bcrypt(Str::random(32)),
            'is_active' => true,
        ]);
        $this->admin->assignRole('admin');
    }

    protected function makeTeacher(): Teacher
    {
        $user = User::forceCreate([
            'name' => 'Feedback Probe',
            'email' => 'feedback-probe-' . Str::random(10) . '@example.test',
            'password' => bcrypt(Str::random(32)),
            'is_active' => true,
        ]);

        return Teacher::forceCreate([
            'user_id' => $user->id,
            'first_name' => 'Feedback',
            'last_name' => 'Probe',
            'webpage' => 'feedback-probe-' . Str::random(8),
            'department_id' => Department::query()->value('id'),
            'designation_id' => Designation::query()->value('id'),
            'is_active' => true,
            'is_archived' => false,
        ]);
    }

    protected function submit(?User $user = null): Feedback
    {
        return Feedback::submit($user ?? $this->teacherUser, [
            'type' => 'bug',
            'subject' => 'Save button gives an error',
            'message' => 'Pressing save on publications shows a 500 page.',
        ]);
    }

    public function test_a_teacher_sends_feedback_and_the_team_is_told(): void
    {
        $this->actingAs($this->teacherUser);

        Livewire::test(CreateMyFeedback::class)
            ->fillForm([
                'type' => 'feature',
                'subject' => 'Export my CV as Word',
                'message' => 'A .docx download next to the PDF one would help.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $feedback = Feedback::where('user_id', $this->teacherUser->id)->latest('id')->firstOrFail();

        $this->assertSame(Feedback::STATUS_NEW, $feedback->status);
        $this->assertSame(Feedback::WAITING_ON_STAFF, $feedback->waiting_on);
        $this->assertSame('Feedback Probe', $feedback->submitter_name);

        Notification::assertSentTo($this->admin, FeedbackReceived::class);
        Notification::assertNotSentTo($this->teacherUser, FeedbackReceived::class);
    }

    public function test_switching_feedback_off_hides_the_menu_and_refuses_the_pages(): void
    {
        $this->actingAs($this->teacherUser);
        $this->assertTrue(MyFeedbackResource::canViewAny());

        Setting::set(Feedback::ENABLED_SETTING, false);

        $this->assertFalse(MyFeedbackResource::canViewAny());
        $this->assertFalse(MyFeedbackResource::canCreate());

        // The team keeps the inbox either way.
        $this->actingAs($this->admin);
        $this->assertTrue(FeedbackResource::canViewAny());
    }

    public function test_a_teacher_sees_only_their_own_feedback(): void
    {
        $mine = $this->submit();
        $theirs = $this->submit($this->makeTeacher()->user);

        $this->actingAs($this->teacherUser);

        $this->assertTrue(MyFeedbackResource::canView($mine));
        $this->assertFalse(MyFeedbackResource::canView($theirs));
        $this->assertFalse(FeedbackResource::canViewAny());

        Livewire::test(ListMyFeedback::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_marking_it_done_asks_the_teacher_to_check_by_panel_and_email(): void
    {
        $feedback = $this->submit();

        $this->actingAs($this->admin);

        Livewire::test(ViewFeedback::class, ['record' => $feedback->getRouteKey()])
            ->callAction('reply', [
                'status' => Feedback::STATUS_RESOLVED,
                'body' => 'Fixed — the save works again.',
            ])
            ->assertHasNoActionErrors();

        $feedback->refresh();
        $this->assertSame(Feedback::STATUS_RESOLVED, $feedback->status);
        $this->assertSame(Feedback::WAITING_ON_SUBMITTER, $feedback->waiting_on);
        $this->assertNotNull($feedback->resolved_at);

        Notification::assertSentTo(
            $this->teacherUser,
            FeedbackUpdated::class,
            fn (FeedbackUpdated $n, array $channels): bool => $channels === ['database', 'mail'],
        );
    }

    public function test_with_email_off_the_teacher_is_told_in_the_panel_only(): void
    {
        Setting::set(Feedback::EMAIL_UPDATES_SETTING, false);
        $feedback = $this->submit();

        $feedback->staffReply($this->admin, 'Looking into it.', Feedback::STATUS_IN_REVIEW);

        Notification::assertSentTo(
            $this->teacherUser,
            FeedbackUpdated::class,
            fn (FeedbackUpdated $n, array $channels): bool => $channels === ['database'],
        );
    }

    public function test_an_internal_note_is_kept_from_the_teacher(): void
    {
        $feedback = $this->submit();

        $feedback->staffReply($this->admin, 'Probably the quartile lookup again.', Feedback::STATUS_RESOLVED, internal: true);

        $feedback->refresh();
        $this->assertSame(Feedback::STATUS_NEW, $feedback->status, 'a note must not move the status');
        Notification::assertNotSentTo($this->teacherUser, FeedbackUpdated::class);

        $this->actingAs($this->teacherUser);
        Livewire::test(ViewMyFeedback::class, ['record' => $feedback->getRouteKey()])
            ->assertSee('Pressing save on publications')
            ->assertDontSee('quartile lookup');

        $this->actingAs($this->admin);
        Livewire::test(ViewFeedback::class, ['record' => $feedback->getRouteKey()])
            ->assertSee('quartile lookup');
    }

    public function test_the_teacher_confirms_the_fix_and_it_closes(): void
    {
        $feedback = $this->submit();
        $feedback->staffReply($this->admin, 'Fixed.', Feedback::STATUS_RESOLVED);

        $this->actingAs($this->teacherUser);

        Livewire::test(ViewMyFeedback::class, ['record' => $feedback->getRouteKey()])
            ->assertActionVisible('confirm')
            ->assertActionVisible('reopen')
            ->assertActionHidden('comment')
            ->callAction('confirm', []);

        $feedback->refresh();
        $this->assertSame(Feedback::STATUS_CLOSED, $feedback->status);
        $this->assertNull($feedback->waiting_on);
        $this->assertNotNull($feedback->closed_at);
        Notification::assertSentTo($this->admin, FeedbackReceived::class, fn (FeedbackReceived $n): bool => $n->title === 'Feedback confirmed fixed');
    }

    public function test_the_inbox_opens_on_what_is_waiting_for_the_team(): void
    {
        $waiting = $this->submit();
        $answered = $this->submit();
        $answered->staffReply($this->admin, 'Done.', Feedback::STATUS_RESOLVED);

        $this->actingAs($this->admin);

        Livewire::test(\App\Filament\Resources\Feedback\Pages\ListFeedback::class)
            ->assertCanSeeTableRecords([$waiting])
            ->assertCanNotSeeTableRecords([$answered]);
    }

    public function test_the_email_reads_as_a_request_to_check(): void
    {
        $feedback = $this->submit();
        $message = $feedback->staffReply($this->admin, 'Fixed — the save works again.', Feedback::STATUS_RESOLVED);

        $mail = (new FeedbackUpdated($feedback->fresh(), $message))->toMail($this->teacherUser);
        $html = (string) $mail->render();

        $this->assertStringContainsString('Done — please check', $mail->subject);
        $this->assertStringContainsString('Fixed — the save works again.', $html);
        $this->assertStringContainsString('/admin/my-feedback/' . $feedback->id, $mail->actionUrl);
    }

    public function test_still_not_working_sends_it_back_to_the_team(): void
    {
        $feedback = $this->submit();
        $feedback->staffReply($this->admin, 'Fixed.', Feedback::STATUS_RESOLVED);

        $this->actingAs($this->teacherUser);

        Livewire::test(ViewMyFeedback::class, ['record' => $feedback->getRouteKey()])
            ->callAction('reopen', ['body' => 'Still a 500 on the second save.'])
            ->assertHasNoActionErrors();

        $feedback->refresh();
        $this->assertSame(Feedback::STATUS_IN_PROGRESS, $feedback->status);
        $this->assertSame(Feedback::WAITING_ON_STAFF, $feedback->waiting_on);
        $this->assertSame(1, $feedback->reopened_count);
        $this->assertSame(1, Feedback::query()->waitingOnStaff()->whereKey($feedback->id)->count());
    }
}
