<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class RestrictedAccountActionException extends AccessDeniedHttpException {}
