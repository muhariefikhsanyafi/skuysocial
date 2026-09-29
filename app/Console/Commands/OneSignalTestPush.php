<?php

namespace App\Console\Commands;

use App\Enums\OneSignalPushOutcome;
use App\Models\User;
use App\Services\OneSignalPushService;
use Illuminate\Console\Command;

class OneSignalTestPush extends Command
{
    protected $signature = 'push:onesignal-test {user : Internal SkuySocial user ID}';

    protected $description = 'Send a neutral OneSignal test push to one SkuySocial user';

    public function handle(OneSignalPushService $push): int
    {
        if (! $push->isConfigured()) {
            $this->error(__('push.command.configuration_missing'));

            return self::FAILURE;
        }

        $userId = filter_var($this->argument('user'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $user = $userId === false ? null : User::query()->find($userId);

        if (! $user instanceof User) {
            $this->error(__('push.command.user_not_found'));

            return self::FAILURE;
        }

        $result = $push->sendToUsers(
            [$user],
            [
                'en' => __('push.test.heading', [], 'en'),
                'id' => __('push.test.heading', [], 'id'),
            ],
            [
                'en' => __('push.test.body', [], 'en'),
                'id' => __('push.test.body', [], 'id'),
            ],
            route('activity.index'),
        );

        return match ($result) {
            OneSignalPushOutcome::Sent => $this->reportSent(),
            OneSignalPushOutcome::NoSubscribers => $this->reportNoSubscribers(),
            OneSignalPushOutcome::ConfigurationMissing => $this->reportConfigurationMissing(),
            OneSignalPushOutcome::Failed => $this->reportFailure(),
        };
    }

    private function reportSent(): int
    {
        $this->info(__('push.command.sent'));

        return self::SUCCESS;
    }

    private function reportNoSubscribers(): int
    {
        $this->warn(__('push.command.no_subscribers'));

        return self::FAILURE;
    }

    private function reportConfigurationMissing(): int
    {
        $this->error(__('push.command.configuration_missing'));

        return self::FAILURE;
    }

    private function reportFailure(): int
    {
        $this->error(__('push.command.failed'));

        return self::FAILURE;
    }
}
