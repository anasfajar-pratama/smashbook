<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_redirects_to_dashboard_or_login(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
    }
}
