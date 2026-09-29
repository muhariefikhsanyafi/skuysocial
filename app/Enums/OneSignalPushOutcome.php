<?php

namespace App\Enums;

enum OneSignalPushOutcome: string
{
    case ConfigurationMissing = 'configuration_missing';
    case Sent = 'sent';
    case NoSubscribers = 'no_subscribers';
    case Failed = 'failed';
}
