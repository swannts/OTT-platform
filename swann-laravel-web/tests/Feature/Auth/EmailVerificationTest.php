<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    public function test_laravel_does_not_expose_browser_email_verification_routes(): void
    {
        $this->get('/verify-email')->assertNotFound();
        $this->get('/email/verify/1/invalid')->assertNotFound();
    }
}
