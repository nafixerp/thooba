<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Setting;
use App\Services\AccountingService;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        protected AccountingService $accounts,
        protected OtpService $otp,
        protected SmsService $sms,
    ) {}

    /** POST /api/v1/auth/register */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', 'unique:customers,phone'],
            'email' => ['nullable', 'email', 'max:120'],
            'password' => ['required', 'min:6'],
            'referred_by' => ['nullable', 'string', 'max:30'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'agent_id' => ['nullable', 'exists:agents,id'],
        ]);

        $data['phone'] = $this->sms->normalize($data['phone']);
        $data['reg_no'] = Customer::nextRegNo();
        $data['must_change_password'] = false;
        $data['referral_code'] = 'REF'.strtoupper(substr(md5($data['phone'].microtime()), 0, 6));
        $data['join_date'] = now()->toDateString();
        $data['status'] = true;
        // Downloaded the app and signed themselves up.
        $data['source'] = 'APP';

        $customer = Customer::create($data);
        $this->accounts->ledgerForCustomer($customer);

        $this->applyReferral($customer, $data['referred_by'] ?? null);
        $this->touchFcm($customer, $request);

        return $this->tokenResponse($customer->fresh(), 'Registration successful');
    }

    /**
     * POST /api/v1/auth/login
     *
     * Accepts phone or reg_no. Customers created from the admin panel may have
     * no password yet - for them the phone number itself is the password, and
     * the response carries must_change_password so the app forces a reset.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $customer = $this->findByLogin($data['phone']);

        if (! $customer || ! $this->passwordMatches($customer, $data['password'])) {
            return response()->json(['success' => false, 'message' => 'Invalid phone or password'], 401);
        }

        if (! $customer->status) {
            return response()->json(['success' => false, 'message' => 'Account is inactive'], 403);
        }

        $this->touchFcm($customer, $request);

        return $this->tokenResponse($customer->fresh(), 'Login successful');
    }

    /**
     * POST /api/v1/auth/otp/request
     * Body: phone, purpose (LOGIN|REGISTER|RESET)
     */
    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['nullable', Rule::in(['LOGIN', 'REGISTER', 'RESET'])],
        ]);

        $purpose = $data['purpose'] ?? 'LOGIN';
        $phone = $this->sms->normalize($data['phone']);
        $exists = Customer::where('phone', $phone)->exists();

        if ($purpose === 'LOGIN' && Setting::get('otp_login', '1') === '0') {
            return response()->json([
                'success' => false,
                'message' => 'OTP login is disabled. Please use your password.',
            ], 422);
        }

        if ($purpose === 'LOGIN' && ! $exists) {
            return response()->json([
                'success' => false,
                'message' => 'This number is not registered. Please register first.',
                'registered' => false,
            ], 404);
        }

        if ($purpose === 'REGISTER' && $exists) {
            return response()->json([
                'success' => false,
                'message' => 'This number is already registered. Please login.',
                'registered' => true,
            ], 422);
        }

        try {
            $result = $this->otp->issue($phone, $purpose, $request->ip());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to '.substr($phone, 0, 2).'xxxxx'.substr($phone, -3),
            'data' => array_filter($result, fn ($v) => $v !== null),
        ]);
    }

    /**
     * POST /api/v1/auth/otp/verify
     * Body: phone, otp - logs the customer in and returns a token.
     */
    public function verifyOtp(Request $request)
    {
        if (Setting::get('otp_login', '1') === '0') {
            return response()->json([
                'success' => false,
                'message' => 'OTP login is disabled. Please use your password.',
            ], 422);
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'max:10'],
        ]);

        $phone = $this->sms->normalize($data['phone']);

        try {
            $this->otp->verify($phone, $data['otp'], 'LOGIN');
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $customer = Customer::where('phone', $phone)->first();

        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'This number is not registered.'], 404);
        }

        if (! $customer->status) {
            return response()->json(['success' => false, 'message' => 'Account is inactive'], 403);
        }

        // No password on file yet -> the phone becomes the default one.
        if (blank($customer->password)) {
            $customer->update(['password' => $customer->phone, 'must_change_password' => true]);
        }

        $this->touchFcm($customer, $request);

        return $this->tokenResponse($customer->fresh(), 'Login successful');
    }

    /**
     * POST /api/v1/auth/otp/register
     * OTP verified self registration. Password defaults to the phone number.
     */
    public function registerWithOtp(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'max:10'],
            'email' => ['nullable', 'email', 'max:120'],
            'password' => ['nullable', 'min:6'],
            'referred_by' => ['nullable', 'string', 'max:30'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'agent_id' => ['nullable', 'exists:agents,id'],
        ]);

        $phone = $this->sms->normalize($data['phone']);

        if (Customer::where('phone', $phone)->exists()) {
            return response()->json(['success' => false, 'message' => 'This number is already registered.'], 422);
        }

        try {
            $this->otp->verify($phone, $data['otp'], 'REGISTER');
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $usedDefault = blank($data['password'] ?? null);

        $customer = Customer::create([
            'reg_no' => Customer::nextRegNo(),
            'name' => $data['name'],
            'phone' => $phone,
            'email' => $data['email'] ?? null,
            // Default password is the phone number until the customer changes it.
            'password' => $usedDefault ? $phone : $data['password'],
            'must_change_password' => $usedDefault,
            'source' => 'APP',
            'referred_by' => $data['referred_by'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'agent_id' => $data['agent_id'] ?? null,
            'referral_code' => 'REF'.strtoupper(substr(md5($phone.microtime()), 0, 6)),
            'join_date' => now()->toDateString(),
            'status' => true,
        ]);

        $this->accounts->ledgerForCustomer($customer);
        $this->applyReferral($customer, $data['referred_by'] ?? null);
        $this->touchFcm($customer, $request);

        return $this->tokenResponse($customer->fresh(), 'Registration successful');
    }

    /**
     * POST /api/v1/auth/forgot-password
     * Body: phone, otp, password - OTP verified password reset.
     */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'max:10'],
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $phone = $this->sms->normalize($data['phone']);

        try {
            $this->otp->verify($phone, $data['otp'], 'RESET');
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $customer = Customer::where('phone', $phone)->first();

        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'This number is not registered.'], 404);
        }

        $customer->update(['password' => $data['password'], 'must_change_password' => false]);
        $customer->tokens()->delete();   // log out every other device

        return response()->json(['success' => true, 'message' => 'Password reset. Please login.']);
    }

    /** POST /api/v1/auth/logout */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    /** GET /api/v1/auth/me */
    public function me(Request $request)
    {
        $c = $request->user()->load('point', 'agent:id,name,phone', 'district:id,name');

        return response()->json(['success' => true, 'data' => $this->profile($c)]);
    }

    /** PUT /api/v1/auth/profile */
    public function updateProfile(Request $request)
    {
        $customer = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'dob' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', Rule::in(['MALE', 'FEMALE', 'OTHER'])],
            'address' => ['sometimes', 'nullable', 'string'],
            'pincode' => ['sometimes', 'nullable', 'string', 'max:10'],
            'district_id' => ['sometimes', 'nullable', 'exists:districts,id'],
            'nominee_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'nominee_relation' => ['sometimes', 'nullable', 'string', 'max:60'],
            'nominee_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'account_no' => ['sometimes', 'nullable', 'string', 'max:40'],
            'ifsc' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('customers', 'public');
        }

        $customer->update($data);

        return response()->json(['success' => true, 'message' => 'Profile updated', 'data' => $this->profile($customer->fresh())]);
    }

    /** POST /api/v1/auth/change-password */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $customer = $request->user();

        if (! $this->passwordMatches($customer, $data['current_password'])) {
            return response()->json(['success' => false, 'message' => 'Current password is wrong'], 422);
        }

        $customer->update(['password' => $data['password'], 'must_change_password' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed',
            'data' => $this->profile($customer->fresh()),
        ]);
    }

    protected function tokenResponse(Customer $customer, string $message)
    {
        $token = $customer->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => $message,
            'token' => $token,
            'token_type' => 'Bearer',
            'data' => $this->profile($customer),
        ]);
    }

    protected function profile(Customer $c): array
    {
        $privilege = app(\App\Services\PrivilegeService::class)->summary($c);

        return [
            'id' => $c->id,
            'reg_no' => $c->reg_no,
            'name' => $c->name,
            'phone' => $c->phone,
            'email' => $c->email,
            'photo' => $c->photo ? storage_url($c->photo) : null,
            'dob' => $c->dob?->toDateString(),
            'gender' => $c->gender,
            'address' => $c->address,
            'district' => $c->district?->name,
            'kyc_status' => $c->kyc_status,
            'referral_code' => $c->referral_code,
            'wallet_amount' => (float) $c->wallet_amount,
            'total_gram' => (float) $c->total_gram,
            'points' => (float) ($c->point->balance_point ?? 0),
            'privilege' => $privilege,
            'agent' => $c->agent ? ['name' => $c->agent->name, 'phone' => $c->agent->phone] : null,
            'join_date' => $c->join_date?->toDateString(),
            // True while the customer is still on the phone-number default.
            'must_change_password' => (bool) $c->must_change_password,
            'active_schemes' => $c->schemes()->where('status', 'ACTIVE')->count(),
        ];
    }

    /* =====================================================================
     |  helpers
     ===================================================================== */

    /** Look a customer up by phone (in any format) or by registration number. */
    protected function findByLogin(string $login): ?Customer
    {
        $phone = $this->sms->normalize($login);

        return Customer::where('phone', $phone)
            ->orWhere('phone', $login)
            ->orWhere('reg_no', $login)
            ->first();
    }

    /**
     * Password check that also accepts the phone number as the default
     * password for accounts the admin created without one.
     */
    protected function passwordMatches(Customer $customer, string $password): bool
    {
        if (filled($customer->password) && Hash::check($password, $customer->password)) {
            return true;
        }

        if (blank($customer->password) && $this->sms->normalize($password) === $customer->phone) {
            // First login on an admin-created account: seed the default password.
            $customer->update(['password' => $customer->phone, 'must_change_password' => true]);

            return true;
        }

        return false;
    }

    protected function touchFcm(Customer $customer, Request $request): void
    {
        if ($request->filled('fcm_token')) {
            $customer->update(['fcm_token' => $request->fcm_token]);
        }
    }

    protected function applyReferral(Customer $customer, ?string $code): void
    {
        if (blank($code)) {
            return;
        }

        $referrer = Customer::where('referral_code', $code)->first();

        if (! $referrer) {
            return;
        }

        $points = (float) Setting::get('referral_points', 100);
        app(\App\Services\SchemeService::class)
            ->addPoints($referrer, $points, 'REFERRAL', 'Referred '.$customer->name);

        \App\Models\Referral::create([
            'code' => $code,
            'customer_id' => $referrer->id,
            'referred_customer_id' => $customer->id,
            'points' => $points,
            'referral_date' => now()->toDateString(),
        ]);
    }
}
