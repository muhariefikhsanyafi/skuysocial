<?php

namespace App\Actions;

use App\Enums\AppreciationType;
use App\Enums\PostAppreciationStatus;

final readonly class PostAppreciationResult
{
    public function __construct(
        public PostAppreciationStatus $status,
        public ?AppreciationType $type = null,
    ) {
    }
}
