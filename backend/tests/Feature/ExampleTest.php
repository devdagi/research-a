<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_redirects_root_to_admin_login(): void
    {
        $this->get('/')->assertRedirect(route('admin.login'));
    }

    public function test_health_endpoint_is_ok(): void
    {
        $this->get('/up')->assertOk();
    }
}
