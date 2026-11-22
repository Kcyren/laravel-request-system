<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('requests.index'));
        $this->get(route('requests.index'))->assertRedirect(route('login'));
    }
}
