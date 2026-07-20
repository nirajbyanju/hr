<?php

namespace Tests\Feature\Platform;

use App\Models\CentralUser;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The platform usage report reads the denormalised counters on `companies`, so
 * it needs no tenant database at all — which is the point: one unreachable
 * tenant must never be able to take the report down.
 */
class UsageReportTest extends TestCase
{
    use RefreshDatabase;

    private function centralAdmin(): CentralUser
    {
        return CentralUser::query()->create([
            'name' => 'Platform Admin',
            'email' => 'super@example.test',
            'password' => Hash::make('P@ssword123'),
            'is_active' => true,
        ]);
    }

    /**
     * A company row without firing TenantCreated — no database is provisioned,
     * because the report never opens a tenant connection.
     */
    private function company(array $attributes): Company
    {
        return Company::withoutEvents(function () use ($attributes): Company {
            $company = new Company();

            $company->forceFill(array_merge([
                'id' => (string) Str::uuid(),
                'status' => 'active',
            ], $attributes));

            $company->save();

            return $company;
        });
    }

    public function test_the_report_shows_accounts_against_the_limit(): void
    {
        $this->company([
            'name' => 'Kathmandu Traders',
            'slug' => 'ktm',
            'domain' => 'ktm.test',
            'user_limit' => 10,
            'users_count' => 4,
            'employees_count' => 3,
        ]);

        $response = $this->actingAs($this->centralAdmin(), 'central')
            ->get(route('platform.reports.usage'));

        $response->assertOk();
        $response->assertSee('Kathmandu Traders');
        $response->assertSee('4 / 10');
        $response->assertSee('40% used');
    }

    public function test_a_company_with_no_limit_reports_as_unlimited(): void
    {
        $this->company([
            'name' => 'No Cap Co',
            'slug' => 'nocap',
            'domain' => 'nocap.test',
            'user_limit' => null,
            'users_count' => 7,
        ]);

        $this->actingAs($this->centralAdmin(), 'central')
            ->get(route('platform.reports.usage'))
            ->assertOk()
            ->assertSee('Unlimited');
    }

    /**
     * A cap lowered below the current headcount must not render as an
     * impossible percentage; it reads as full, with no seats left.
     */
    public function test_a_company_over_its_limit_reads_as_full(): void
    {
        $this->company([
            'name' => 'Over Cap Co',
            'slug' => 'overcap',
            'domain' => 'overcap.test',
            'user_limit' => 5,
            'users_count' => 9,
        ]);

        $response = $this->actingAs($this->centralAdmin(), 'central')
            ->get(route('platform.reports.usage'));

        $response->assertOk();
        $response->assertSee('100% used');
        $response->assertSee('Full');
        $response->assertDontSee('180% used');
    }

    public function test_the_report_exports_as_csv(): void
    {
        $this->company([
            'name' => 'Kathmandu Traders',
            'slug' => 'ktm',
            'domain' => 'ktm.test',
            'user_limit' => 10,
            'users_count' => 4,
        ]);

        $response = $this->actingAs($this->centralAdmin(), 'central')
            ->get(route('platform.reports.usage.export'));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Company,Domain', $csv);
        $this->assertStringContainsString('Kathmandu Traders', $csv);
        $this->assertStringContainsString('40%', $csv);
    }

    public function test_the_report_is_closed_to_guests(): void
    {
        $this->get(route('platform.reports.usage'))->assertRedirect(route('platform.login'));
        $this->get(route('platform.reports.usage.export'))->assertRedirect(route('platform.login'));
    }
}
