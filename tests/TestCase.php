<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base case for tests that only touch the CENTRAL database — companies,
 * platform administrators, the console.
 *
 * Anything that touches tenant data (users, employees, tasks, payroll…) must
 * extend Tests\TenantTestCase instead, which provisions a real tenant database.
 */
abstract class TestCase extends BaseTestCase
{
    //
}
