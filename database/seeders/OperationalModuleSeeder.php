<?php

namespace Database\Seeders;

use App\Models\AppVersion;
use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scheme;
use Illuminate\Database\Seeder;

class OperationalModuleSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::updateOrCreate(['code' => 'MAIN'], [
            'name' => 'Main Branch',
            'manager_name' => 'Branch Manager',
            'phone' => '+91 98470 00000',
            'email' => 'info@ishqjewellery.com',
            'address' => 'Main Road, Kochi, Kerala 682001',
            'city' => 'Kochi',
            'state' => 'Kerala',
            'country' => 'India',
            'pincode' => '682001',
            'status' => true,
        ]);

        $permissionRows = [
            ['module' => 'dashboard', 'name' => 'View Dashboard', 'slug' => 'dashboard.view'],
            ['module' => 'customers', 'name' => 'Manage Customers', 'slug' => 'customers.manage'],
            ['module' => 'agents', 'name' => 'Manage Agents', 'slug' => 'agents.manage'],
            ['module' => 'schemes', 'name' => 'Manage Schemes', 'slug' => 'schemes.manage'],
            ['module' => 'payments', 'name' => 'Manage Payments', 'slug' => 'payments.manage'],
            ['module' => 'accounts', 'name' => 'Manage Accounts', 'slug' => 'accounts.manage'],
            ['module' => 'reports', 'name' => 'View Reports', 'slug' => 'reports.view'],
            ['module' => 'support', 'name' => 'Manage Support Tickets', 'slug' => 'support.manage'],
            ['module' => 'settings', 'name' => 'Manage Settings', 'slug' => 'settings.manage'],
        ];

        $permissions = collect($permissionRows)->mapWithKeys(fn ($row) => [
            $row['slug'] => Permission::updateOrCreate(['slug' => $row['slug']], $row + ['status' => true]),
        ]);

        $roles = [
            'superadmin' => ['name' => 'Super Admin', 'is_system' => true, 'permissions' => $permissions->keys()->all()],
            'admin' => ['name' => 'Admin', 'is_system' => true, 'permissions' => ['dashboard.view', 'customers.manage', 'agents.manage', 'schemes.manage', 'payments.manage', 'reports.view', 'support.manage']],
            'accountant' => ['name' => 'Accountant', 'is_system' => true, 'permissions' => ['dashboard.view', 'payments.manage', 'accounts.manage', 'reports.view']],
            'staff' => ['name' => 'Staff', 'is_system' => true, 'permissions' => ['dashboard.view', 'customers.manage', 'support.manage']],
        ];

        foreach ($roles as $slug => $data) {
            $role = Role::updateOrCreate(['slug' => $slug], [
                'name' => $data['name'],
                'is_system' => $data['is_system'],
                'status' => true,
            ]);

            $role->permissions()->sync(
                collect($data['permissions'])->map(fn ($slug) => $permissions[$slug]->id)->all()
            );
        }

        CommissionRule::updateOrCreate(['name' => 'Default Agent Commission'], [
            'branch_id' => $mainBranch->id,
            'scheme_id' => Scheme::query()->value('id'),
            'basis' => 'PERCENT',
            'rate' => 1.50,
            'effective_from' => now()->toDateString(),
            'status' => true,
        ]);

        AppVersion::updateOrCreate(['platform' => 'ANDROID', 'build_number' => 1], [
            'version' => '1.0.0',
            'min_supported_version' => '1.0.0',
            'min_supported_build' => 1,
            'force_update' => false,
            'maintenance_mode' => false,
            'status' => true,
        ]);

        $this->command?->info('  Operational module data seeded.');
    }
}
