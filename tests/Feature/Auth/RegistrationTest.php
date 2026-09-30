<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = Volt::test('auth.register')
            ->set('name', 'Test User')
            ->set('username', 'test_user')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $response
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'test_user']);
    }

    public function test_username_must_be_unique_and_well_formed(): void
    {
        User::factory()->create(['username' => 'taken']);

        Volt::test('auth.register')
            ->set('name', 'Test User')
            ->set('username', 'taken')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors(['username' => 'unique']);

        Volt::test('auth.register')
            ->set('username', 'Invalid-Name!')
            ->call('register')
            ->assertHasErrors(['username' => 'regex']);
    }
}
