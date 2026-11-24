<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_laravel_does_not_expose_browser_registration_routes(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'first_name' => 'Viewer',
            'last_name' => 'Example',
            'email' => 'viewer@example.test',
            'password' => 'example-password',
            'password_confirmation' => 'example-password',
        ])->assertNotFound();
    }
}
