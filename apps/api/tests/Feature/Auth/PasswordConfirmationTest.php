<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    public function test_laravel_does_not_expose_browser_password_confirmation_routes(): void
    {
        $this->get('/confirm-password')->assertNotFound();
        $this->post('/confirm-password', ['password' => 'example-password'])->assertNotFound();
    }
}
