<?php

namespace App\Enums;

enum MessagePrivacy: string
{
    case Everyone = 'everyone';
    case PeopleIFollow = 'people_i_follow';
    case Nobody = 'nobody';
}
