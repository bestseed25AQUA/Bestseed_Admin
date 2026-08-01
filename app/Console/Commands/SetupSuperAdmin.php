<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetupSuperAdmin extends Command
{
    protected $signature = 'admin:setup {--email= : The email for the super admin} {--make-existing : Make an existing user a super admin}';

    protected $description = 'Setup a super admin user for the application';

    public function handle()
    {
        // First, ensure roles and permissions are seeded
        $this->info('Checking roles and permissions...');

        if (Role::count() === 0) {
            $this->call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
            $this->info('Roles and permissions seeded.');
        }

        if ($this->option('make-existing')) {
            $this->makeExistingUserSuperAdmin();
        } else {
            $this->createNewSuperAdmin();
        }

        return 0;
    }

    protected function makeExistingUserSuperAdmin()
    {
        $email = $this->option('email') ?? $this->ask('Enter the email of the existing user');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return;
        }

        $user->update([
            'is_admin' => true,
            'is_super_admin' => true,
        ]);

        // Assign super-admin role
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $user->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }

        $this->info("User {$user->name} ({$user->email}) has been made a Super Admin.");
    }

    protected function createNewSuperAdmin()
    {
        $email = $this->option('email') ?? $this->ask('Enter email for the super admin');

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists. Use --make-existing flag to upgrade them.");
            return;
        }

        $name = $this->ask('Enter name for the super admin');
        $password = $this->secret('Enter password for the super admin');
        $confirmPassword = $this->secret('Confirm password');

        if ($password !== $confirmPassword) {
            $this->error('Passwords do not match.');
            return;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
            'is_super_admin' => true,
        ]);

        // Assign super-admin role
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $user->roles()->attach($superAdminRole->id);
        }

        $this->info('Super Admin created successfully!');
        $this->table(
            ['Name', 'Email', 'Role'],
            [[$user->name, $user->email, 'Super Admin']]
        );
    }
}
