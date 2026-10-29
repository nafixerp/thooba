<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ApkDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_an_apk_and_anyone_can_download_it(): void
    {
        URL::forceRootUrl('http://localhost');
        Storage::fake('public');

        $admin = User::create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@example.test',
            'password' => 'secret123',
            'role' => 'SUPERADMIN',
            'status' => true,
        ]);

        $this->actingAs($admin)->post('/admin/settings/android-app', [
            'apk' => UploadedFile::fake()->create(
                'scheme-erp.apk',
                1024,
                'application/vnd.android.package-archive'
            ),
            'apk_version' => '1.2.3',
        ])->assertRedirect();

        Storage::disk('public')->assertExists('releases/scheme-erp.apk');
        $this->assertSame('1.2.3', Setting::get('android_apk_version'));

        $this->get('/download/app')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.android.package-archive');
    }

    public function test_download_returns_not_found_until_an_apk_is_uploaded(): void
    {
        URL::forceRootUrl('http://localhost');
        Storage::fake('public');

        $this->get('/download/app')->assertNotFound();
    }
}
