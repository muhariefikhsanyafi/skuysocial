<?php

namespace App\Enums;

enum PostAppreciationStatus: string
{
    case Appreciated = 'appreciated';
    case AlreadyAppreciated = 'already_appreciated';
    case InsufficientPoints = 'insufficient_points';
    case SelfAppreciationForbidden = 'self_appreciation_forbidden';
}
