<?php

namespace App\Enums;

enum ProfileVisibility: string
{
    case Public = 'public';
    case MembersOnly = 'members_only';
}
