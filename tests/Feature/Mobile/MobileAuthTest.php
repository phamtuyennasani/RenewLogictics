<?php

namespace Tests\Feature\Mobile;

use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use BuildsMobileSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMobileSchema();
    }

    protected function tearDown(): void
    {
        $this->dropMobileSchema();
        parent::tearDown();
    }

    public function test_shipper_login_succeeds_and_returns_shipper_module(): void
    {
        $this->makeUser(['shipper'], ['username' => 'shipper01']);

        $this->postJson('/api/mobile/login', [
            'username' => 'shipper01',
            'password' => 'secret',
            'device_name' => 'Pixel 7',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.default_module', 'shipper')
            ->assertJsonPath('data.roles.0', 'shipper')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'username'], 'roles', 'permissions']]);
    }

    public function test_ops_user_gets_ops_module(): void
    {
        $this->makeUser(['ops'], ['username' => 'ops01']);

        $this->postJson('/api/mobile/login', ['username' => 'ops01', 'password' => 'secret'])
            ->assertOk()
            ->assertJsonPath('data.default_module', 'ops');
    }

    public function test_user_with_both_roles_gets_chooser(): void
    {
        $this->makeUser(['shipper', 'ops'], ['username' => 'both01']);

        $this->postJson('/api/mobile/login', ['username' => 'both01', 'password' => 'secret'])
            ->assertOk()
            ->assertJsonPath('data.default_module', 'chooser');
    }

    public function test_wrong_password_returns_401(): void
    {
        $this->makeUser(['shipper'], ['username' => 'shipper01']);

        $this->postJson('/api/mobile/login', ['username' => 'shipper01', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_inactive_account_returns_403(): void
    {
        $this->makeUser(['shipper'], ['username' => 'locked01', 'status' => false]);

        $this->postJson('/api/mobile/login', ['username' => 'locked01', 'password' => 'secret'])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_user_without_mobile_role_is_rejected(): void
    {
        $this->makeUser(['ctv'], ['username' => 'ctv01']);

        $this->postJson('/api/mobile/login', ['username' => 'ctv01', 'password' => 'secret'])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_validation_error_returns_422(): void
    {
        $this->postJson('/api/mobile/login', ['username' => ''])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['username']]);
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/mobile/me')->assertUnauthorized();
    }

    public function test_me_returns_current_user(): void
    {
        $user = $this->makeUser(['shipper'], ['username' => 'shipper01']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/mobile/me')
            ->assertOk()
            ->assertJsonPath('data.user.username', 'shipper01')
            ->assertJsonPath('data.default_module', 'shipper');
    }

    public function test_logout_revokes_token(): void
    {
        $user = $this->makeUser(['shipper'], ['username' => 'shipper01']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/mobile/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
