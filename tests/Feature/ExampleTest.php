<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guest_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_root_redirects_logged_in_user_by_role(): void
    {
        $this->seed();

        $this->actingAs(User::where('email', 'admin@posme.test')->first())
            ->get('/')
            ->assertRedirect('/admin');

        $this->actingAs(User::where('email', 'kasir@posme.test')->first())
            ->get('/')
            ->assertRedirect('/cashier');

        $this->actingAs(User::where('email', 'dapur@posme.test')->first())
            ->get('/')
            ->assertRedirect('/kitchen');
    }
}
