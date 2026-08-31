<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RepairUsernames extends Command
{
    protected $signature = 'users:repair-usernames
                            {--force : Apply the generated usernames instead of previewing them}';

    protected $description = 'Generate unique usernames for users that do not have one';

    public function handle(): int
    {
        $users = User::query()->whereNull('username')->get();

        if ($users->isEmpty()) {
            $this->info('No usernames need repair.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $username = $this->usernameFor($user);
            $this->line("{$user->id}: {$username}");

            if ($this->option('force')) {
                $user->username = $username;
                $user->save();
            }
        }

        if (! $this->option('force')) {
            $this->warn('Preview only. Re-run with --force to apply these changes.');
        }

        return self::SUCCESS;
    }

    private function usernameFor(User $user): string
    {
        $base = Str::of((string) $user->name)
            ->lower()
            ->replaceMatches('/[^a-z0-9_.]+/', '')
            ->toString();

        $base = $base !== '' ? $base : 'user';
        $username = $base.$user->id;
        $suffix = 1;

        while (User::where('username', $username)->where('id', '!=', $user->getKey())->exists()) {
            $username = $base.$user->id.$suffix;
            $suffix++;
        }

        return Str::limit($username, 255, '');
    }
}
