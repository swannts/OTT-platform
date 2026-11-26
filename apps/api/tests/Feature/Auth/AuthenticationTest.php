<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_laravel_does_not_expose_browser_login_routes(): void
    {
        $this->get('/login')->assertNotFound();
        $this->post('/login', ['email' => 'viewer@example.test', 'password' => 'example-password'])->assertNotFound();
    }
}
