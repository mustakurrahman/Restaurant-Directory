<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Every test starts signed in as the owner, because the whole admin needs a login.
    // A test about signing in (or about visitors who are not signed in) sets this to false.
    protected bool $signedInAsAdmin = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->signedInAsAdmin) {
            // make(), not create(): some test classes have no database, and signing in does not need a saved row
            $this->actingAs(User::factory()->make());
        }
    }
}
