<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\User;
use App\Support\AnnouncementAudience;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user() instanceof User && auth()->user()->isUsableAdministrator(), 403);
        parent::authorizeAccess();
        abort_unless($this->getRecord()->status === Announcement::STATUS_DRAFT, 404);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->getRecord()->audience_type === Announcement::AUDIENCE_TARGETED) {
            $data['target_user_ids'] = $this->getRecord()->targets()
                ->pluck('user_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Gate::authorize('update', $this->getRecord());
        if ($this->getRecord()->status !== Announcement::STATUS_DRAFT) {
            abort(404);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $targetIds = array_values(array_unique(array_map('intval', $data['target_user_ids'] ?? [])));
        unset($data['target_user_ids']);
        DB::transaction(function () use ($record, $data, $targetIds): void {
            abort_unless($record->status === Announcement::STATUS_DRAFT, 404);
            $record->update($data);
            if ($record->audience_type === Announcement::AUDIENCE_TARGETED) {
                try {
                    app(AnnouncementAudience::class)->replaceDraftTargets($record, $targetIds);
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages([
                        'data.target_user_ids' => __('admin_announcements.validation.targets'),
                    ]);
                }
            } else {
                $record->targets()->delete();
            }
        });

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete_draft')
                ->label(__('admin_announcements.actions.delete'))
                ->color('danger')
                ->requiresConfirmation()
                ->authorize('delete', $this->getRecord())
                ->action(function (): void {
                    Gate::authorize('delete', $this->getRecord());
                    abort_unless($this->getRecord()->status === Announcement::STATUS_DRAFT, 404);
                    $this->getRecord()->delete();
                    $this->redirect(AnnouncementResource::getUrl('index'));
                }),
        ];
    }
}
