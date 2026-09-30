<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_install_welcome_page_loads(): void
    {
        $response = $this->get('/install');

        $response->assertStatus(200);
    }
}
