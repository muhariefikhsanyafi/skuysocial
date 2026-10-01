<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\User;
use App\Support\AnnouncementAudience;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user() instanceof User && auth()->user()->isUsableAdministrator(), 403);
    }

    protected function handleRecordCreation(array $data): Model
    {
        Gate::authorize('create', Announcement::class);
        $targetIds = array_values(array_unique(array_map('intval', $data['target_user_ids'] ?? [])));
        unset($data['target_user_ids']);
        $data['status'] = Announcement::STATUS_DRAFT;
        $data['created_by'] = auth()->id();

        return DB::transaction(function () use ($data, $targetIds): Announcement {
            $record = Announcement::query()->create($data);
            if ($record->audience_type === Announcement::AUDIENCE_TARGETED && $targetIds !== []) {
                try {
                    app(AnnouncementAudience::class)->replaceDraftTargets($record, $targetIds);
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages([
                        'data.target_user_ids' => __('admin_announcements.validation.targets'),
                    ]);
                }
            }

            return $record;
        });
    }
}
