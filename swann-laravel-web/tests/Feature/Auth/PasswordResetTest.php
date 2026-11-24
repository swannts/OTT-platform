<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_laravel_does_not_expose_browser_password_reset_routes(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'viewer@example.test'])->assertNotFound();
        $this->get('/reset-password/example-token')->assertNotFound();
        $this->post('/reset-password', [])->assertNotFound();
    }
}
