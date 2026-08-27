<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

class RotateAdminCommand extends Command
{
    /**
     * Documented installer account that must not remain usable in production.
     */
    private const DEFAULT_ADMIN_EMAIL = 'admin@example.com';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'krayin:rotate-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable the installer default admin and upsert the operator from ADMIN_EMAIL / ADMIN_PASSWORD.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $operatorEmail = trim((string) getenv('ADMIN_EMAIL'));
        $operatorPassword = (string) getenv('ADMIN_PASSWORD');

        if ($operatorEmail === '' || $operatorPassword === '') {
            $this->comment('ADMIN_EMAIL or ADMIN_PASSWORD not set; leaving seeded admin unchanged.');

            return self::SUCCESS;
        }

        if (strcasecmp($operatorEmail, self::DEFAULT_ADMIN_EMAIL) === 0) {
            $this->error('ADMIN_EMAIL cannot be the installer default address.');

            return self::FAILURE;
        }

        $roleId = $this->administratorRoleId();

        if ($roleId === null) {
            $this->error('No administrator role found; cannot rotate admin.');

            return self::FAILURE;
        }

        $this->upsertOperator($operatorEmail, $operatorPassword, $roleId);
        $this->disableDefaultAdmin();

        $this->info('Default installer admin disabled; operator admin ensured from environment.');

        return self::SUCCESS;
    }

    private function administratorRoleId(): ?int
    {
        $role = Role::query()->where('permission_type', 'all')->orderBy('id')->first()
            ?? Role::query()->orderBy('id')->first();

        return $role?->id;
    }

    private function upsertOperator(string $email, string $password, int $roleId): void
    {
        $operator = User::query()->where('email', $email)->first();
        $default = User::query()->where('email', self::DEFAULT_ADMIN_EMAIL)->first();

        if (! $operator && $default) {
            $operator = $default;
            $operator->email = $email;
        }

        if (! $operator) {
            $operator = new User;
            $operator->name = 'Administrator';
            $operator->email = $email;
            $operator->role_id = $roleId;
            $operator->view_permission = 'global';
        }

        $operator->name = $operator->name ?: 'Administrator';
        $operator->password = Hash::make($password);
        $operator->status = 1;
        $operator->role_id = $operator->role_id ?: $roleId;
        $operator->view_permission = $operator->view_permission ?: 'global';
        $operator->save();
    }

    private function disableDefaultAdmin(): void
    {
        User::query()
            ->where('email', self::DEFAULT_ADMIN_EMAIL)
            ->get()
            ->each(function (User $user): void {
                $user->status = 0;
                $user->password = Hash::make(Str::password(64));
                $user->email = 'disabled-default-'.$user->id.'@invalid.local';
                $user->save();
            });
    }
}
