@extends('layouts.app')
@section('title', 'Change Password')

@section('content')
<div class="mx-auto max-w-md">
    <div class="card">
        <div class="card-head"><h3 class="card-title">Change Your Password</h3></div>
        <form method="POST" action="{{ route('admin.password.update') }}" class="card-pad space-y-4">
            @csrf
            <div><label class="label">Current Password</label><input type="password" name="current_password" class="input" required></div>
            <div><label class="label">New Password</label><input type="password" name="password" class="input" required minlength="6"></div>
            <div><label class="label">Confirm New Password</label><input type="password" name="password_confirmation" class="input" required></div>
            <button class="btn-primary w-full">Update Password</button>
        </form>
    </div>
</div>
@endsection
