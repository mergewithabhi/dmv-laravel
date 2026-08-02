<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait CreatesCmsUsers
{
    protected function cmsUser(array $permissions, string $role = 'Test Role'): User
    {
        foreach (array_merge(['access admin'], $permissions) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roleModel = Role::findOrCreate($role, 'web');
        $roleModel->syncPermissions(array_merge(['access admin'], $permissions));
        $user = User::factory()->create();
        $user->assignRole($roleModel);

        return $user->refresh();
    }

    protected function enableTwoFactor(User $user): User
    {
        $user->forceFill([
            'two_factor_secret' => 'test-two-factor-secret',
            'two_factor_recovery_codes' => '[]',
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user->refresh();
    }
}
