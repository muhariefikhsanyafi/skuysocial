<?php

namespace App\Filament\Resources\CommentResource\Pages;

use App\Filament\Resources\CommentResource;
use Filament\Resources\Pages\ListRecords;

class ListComments extends ListRecords
{
    protected static string $resource = CommentResource::class;

    public function getTableRecordsPerPage(): int
    {
        return match ($this->tableRecordsPerPage) {
            25, '25' => 25,
            50, '50' => 50,
            100, '100' => 100,
            default => 25,
        };
    }
}
