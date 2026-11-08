<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }
}
