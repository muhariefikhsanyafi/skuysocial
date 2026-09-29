<?php

namespace App\Support;

use App\Models\User;
use InvalidArgumentException;

class OneSignalExternalId
{
    public static function forUser(User $user): string
    {
        $id = filter_var($user->getKey(), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! $user->exists || $id === false) {
            throw new InvalidArgumentException('OneSignal identity requires a persisted user.');
        }

        return 'skuysocial-user:'.$id;
    }
}
