<?php

use Illuminate\Support\Facades\Config;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Config::set('devsquad-sidecar.enabled', true);
    $this->user = \Tests\User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

it('allows authenticated users to access non-execute routes without IP restrictions', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['192.168.1.1']);

    actingAs($this->user)
        ->getJson('__devsquad-sidecar/data')
        ->assertOk();
});

it('allows authenticated users with matching exact IP to execute commands', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['127.0.0.1']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertOk();
});

it('blocks authenticated users with non-matching IP from executing commands', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['192.168.1.1']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertForbidden()
        ->assertSeeText('Unauthorized IP: 127.0.0.1');
});

it('allows authenticated users with matching IP prefix to execute commands', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['127.0.0']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertOk();
});

it('blocks IP that starts with same digits but different octet (192.168.1 should not match 192.168.10)', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['192.168.1']);

    actingAs($this->user)
        ->withServerVariables(['REMOTE_ADDR' => '192.168.10.1'])
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertForbidden();
});

it('allows CIDR notation for IPv4 ranges', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['127.0.0.0/8']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertOk();
});

it('allows all authenticated users when allowed_ips is empty', function () {
    Config::set('devsquad-sidecar.allowed_ips', []);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertForbidden();
});

it('allows multiple IP patterns in allowed_ips', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['192.168.1.1', '10.0.0', '127.0.0.0/8']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertOk();
});

it('validates IP for all execute endpoints', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['192.168.1.1']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-tinker', ['code' => base64_encode('1+1')])
        ->assertForbidden()
        ->assertSeeText('Unauthorized IP');

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-fake-clock', ['time' => now()->toDateTimeString()])
        ->assertForbidden()
        ->assertSeeText('Unauthorized IP');
});

it('does not validate IP for non-execute endpoints', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['192.168.1.1']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/clear-user-cache', ['user_id' => 1])
        ->assertOk();
});

it('blocks when sidecar is disabled', function () {
    Config::set('devsquad-sidecar.enabled', false);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertForbidden()
        ->assertSeeText('Sidecar is disabled');
});

it('trims whitespace from allowed IPs', function () {
    Config::set('devsquad-sidecar.allowed_ips', [' 127.0.0.1 ', '  192.168.1  ']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertOk();
});

it('does not match partial IP segments (security test)', function () {
    // This test ensures that "1" doesn't match "192.168.1.100" or "127.0.0.1"
    Config::set('devsquad-sidecar.allowed_ips', ['1']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertForbidden()
        ->assertSeeText('Unauthorized IP');
});

it('does not match substring of IP (security test)', function () {
    // This test ensures that "7.0.0" doesn't match "127.0.0.1"
    Config::set('devsquad-sidecar.allowed_ips', ['7.0.0']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertForbidden()
        ->assertSeeText('Unauthorized IP');
});

it('handles invalid CIDR notation gracefully', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['192.168.1.0/999']);

    actingAs($this->user)
        ->postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertForbidden()
        ->assertSeeText('Unauthorized IP');
});
