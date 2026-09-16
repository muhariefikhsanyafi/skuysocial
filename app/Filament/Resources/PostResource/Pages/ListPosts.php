<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

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
