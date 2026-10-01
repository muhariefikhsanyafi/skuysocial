<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user() instanceof User && auth()->user()->isUsableAdministrator(), 403);
        parent::authorizeAccess();
    }

    public function getViewData(): array
    {
        return parent::getViewData();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('admin_announcements.actions.create'))];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->withCount([
            'pushBatches as delivery_queued_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('status', 'queued'),
            'pushBatches as delivery_processing_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('status', 'processing'),
            'pushBatches as delivery_sent_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('provider_outcome', 'sent'),
            'pushBatches as delivery_no_subscribers_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('provider_outcome', 'no_subscribers'),
            'pushBatches as delivery_failed_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('provider_outcome', 'failed'),
            'pushBatches as delivery_unknown_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('provider_outcome', 'unknown'),
        ]);
    }
}
