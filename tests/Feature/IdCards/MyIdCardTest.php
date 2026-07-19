<?php

namespace Tests\Feature\IdCards;

use App\Models\Employee;
use App\Models\EmployeeIdCard;
use App\Models\User;
use App\Modules\IdCards\Services\IdCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Self-service ID card: an employee sees their own card and only their own.
 */
class MyIdCardTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(string $email, string $code, string $first): Employee
    {
        $user = new User();
        $user->forceFill([
            'name' => $first,
            'email' => $email,
            'password' => Hash::make('P@ssword123'),
            'account_status' => 'active',
            'approved_at' => now(),
        ])->save();

        $employee = new Employee();
        $employee->forceFill([
            'user_id' => $user->id,
            'employee_code' => $code,
            'first_name' => $first,
            'last_name' => 'Tester',
            'date_of_joining' => '2026-01-01',
            'employment_status' => 'active',
        ])->save();

        return $employee->fresh();
    }

    private function issue(Employee $employee): EmployeeIdCard
    {
        return app(IdCardService::class)->issue($employee, null);
    }

    public function test_an_employee_sees_their_generated_card(): void
    {
        $employee = $this->employeeUser('niraj@samriddhihr.local', 'EMP-001', 'Niraj');
        $card = $this->issue($employee);

        $response = $this->actingAs($employee->user)->get('/my/id-card');

        $response->assertOk();
        $response->assertSee($card->card_number);
        $response->assertSee('Niraj');
    }

    public function test_an_employee_without_a_card_sees_an_empty_state(): void
    {
        $employee = $this->employeeUser('nocard@samriddhihr.local', 'EMP-002', 'Nocard');

        $response = $this->actingAs($employee->user)->get('/my/id-card');

        $response->assertOk();
        $response->assertSee('No ID card has been issued to you yet');
    }

    public function test_an_employee_never_sees_another_employees_card(): void
    {
        $mine = $this->employeeUser('mine@samriddhihr.local', 'EMP-003', 'Mine');
        $theirs = $this->employeeUser('theirs@samriddhihr.local', 'EMP-004', 'Theirs');

        $myCard = $this->issue($mine);
        $theirCard = $this->issue($theirs);

        $response = $this->actingAs($mine->user)->get('/my/id-card');

        $response->assertOk();
        $response->assertSee($myCard->card_number);
        $response->assertDontSee($theirCard->card_number);
    }

    public function test_a_revoked_card_is_not_shown_or_downloadable(): void
    {
        $employee = $this->employeeUser('revoked@samriddhihr.local', 'EMP-005', 'Revoked');
        $card = $this->issue($employee);

        app(IdCardService::class)->revoke($card, null);

        $this->actingAs($employee->user)
            ->get('/my/id-card')
            ->assertOk()
            ->assertSee('No ID card has been issued to you yet');

        $this->actingAs($employee->user)
            ->get('/my/id-card/pdf')
            ->assertRedirect(route('my.id-card'));
    }

    public function test_downloading_records_a_print_log_entry(): void
    {
        $employee = $this->employeeUser('pdf@samriddhihr.local', 'EMP-006', 'Pdf');
        $card = $this->issue($employee);

        $response = $this->actingAs($employee->user)->get('/my/id-card/pdf');

        $response->assertOk();
        $this->assertSame(1, $card->fresh()->print_count);
        $this->assertDatabaseHas('employee_id_card_print_logs', [
            'employee_id_card_id' => $card->id,
            'event' => 'downloaded',
            'format' => 'pdf',
            'performed_by' => $employee->user_id,
        ]);
    }

    public function test_a_user_with_no_employee_record_is_told_to_contact_hr(): void
    {
        $user = new User();
        $user->forceFill([
            'name' => 'Orphan',
            'email' => 'orphan@samriddhihr.local',
            'password' => Hash::make('P@ssword123'),
            'account_status' => 'active',
            'approved_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->get('/my/id-card')
            ->assertOk()
            ->assertSee('not linked to an employee record');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/my/id-card')->assertRedirect(route('login'));
    }
}
