<?php

namespace Tests\Feature;

use App\Models\Agent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AgentAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
    }

    public function test_active_agent_logs_in_with_code_and_mobile_as_first_password(): void
    {
        $agent = $this->agent();

        $this->postJson('/api/v1/agent/login', [
            'agent_code' => strtolower($agent->agent_code),
            'password' => $agent->phone,
        ])
            ->assertOk()
            ->assertJsonPath('role', 'agent')
            ->assertJsonPath('data.agent_code', $agent->agent_code);
    }

    public function test_agent_cannot_log_in_using_phone_as_identifier(): void
    {
        $agent = $this->agent();

        $this->postJson('/api/v1/agent/login', [
            'agent_code' => $agent->phone,
            'password' => $agent->phone,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('agent_code');
    }

    public function test_inactive_agent_cannot_log_in(): void
    {
        $agent = $this->agent(['status' => false]);

        $this->postJson('/api/v1/agent/login', [
            'agent_code' => $agent->agent_code,
            'password' => $agent->phone,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive');
    }

    public function test_deactivated_agent_token_cannot_access_agent_features(): void
    {
        $agent = $this->agent();
        $token = $agent->createToken('agent-app', ['agent'])->plainTextToken;
        $agent->update(['status' => false]);

        $this->withToken($token)
            ->getJson('/api/v1/agent/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'This agent account is inactive.');
    }

    public function test_agent_self_registration_endpoint_does_not_exist(): void
    {
        $this->postJson('/api/v1/agent/register', [
            'agent_code' => 'AGT00001',
        ])->assertNotFound();
    }

    protected function agent(array $overrides = []): Agent
    {
        return Agent::create(array_merge([
            'agent_code' => 'AGT00001',
            'name' => 'Test Agent',
            'phone' => '9847011111',
            'password' => '9847011111',
            'must_change_password' => true,
            'status' => true,
        ], $overrides));
    }
}
