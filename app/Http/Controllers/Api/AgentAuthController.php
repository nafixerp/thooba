<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Agent app login.
 *
 * Agents do not self register. The office creates them; they sign in with the
 * generated agent code and their registered mobile number as first password.
 */
class AgentAuthController extends Controller
{
    /**
     * POST /api/v1/agent/login
     * Body: agent_code, password
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'agent_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'password' => ['required', 'string'],
        ]);

        $agentCode = strtoupper(trim($data['agent_code']));
        $agent = Agent::where('agent_code', $agentCode)->first();

        if (! $agent || ! $this->passwordMatches($agent, $data['password'])) {
            return response()->json(['success' => false, 'message' => 'Invalid login ID or password'], 401);
        }

        if (! $agent->status) {
            return response()->json(['success' => false, 'message' => 'This account is inactive'], 403);
        }

        if ($request->filled('fcm_token')) {
            $agent->update(['fcm_token' => $request->fcm_token]);
        }

        $token = $agent->createToken('agent-app', ['agent'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'token_type' => 'Bearer',
            'role' => 'agent',
            'data' => $this->profile($agent->fresh()),
        ]);
    }

    /** GET /api/v1/agent/me */
    public function me(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->profile($request->user())]);
    }

    /** POST /api/v1/agent/logout */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    /** POST /api/v1/agent/change-password */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $agent = $request->user();

        if (! $this->passwordMatches($agent, $data['current_password'])) {
            return response()->json(['success' => false, 'message' => 'Current password is wrong'], 422);
        }

        $agent->update(['password' => $data['password'], 'must_change_password' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed',
            'data' => $this->profile($agent->fresh()),
        ]);
    }

    /* ===================================================================== */

    /**
     * Password check. Agents created without a password fall back to their
     * phone number once, and are then forced to change it.
     */
    protected function passwordMatches(Agent $agent, string $password): bool
    {
        if (filled($agent->password) && Hash::check($password, $agent->password)) {
            return true;
        }

        if (blank($agent->password) && $this->digits($password) === $this->digits((string) $agent->phone)) {
            $agent->update(['password' => $agent->phone, 'must_change_password' => true]);

            return true;
        }

        return false;
    }

    protected function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    protected function profile(Agent $a): array
    {
        return [
            'id' => $a->id,
            'agent_code' => $a->agent_code,
            'name' => $a->name,
            'username' => $a->username,
            'phone' => $a->phone,
            'email' => $a->email,
            'image' => $a->image ? storage_url($a->image) : null,
            'commission_percent' => (float) $a->commission_percent,
            'join_date' => $a->join_date?->toDateString(),
            'must_change_password' => (bool) $a->must_change_password,
        ];
    }
}
