<?php

namespace App\Enums;

enum PointTransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
