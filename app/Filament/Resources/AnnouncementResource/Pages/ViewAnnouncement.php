<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\User;
use App\Support\AnnouncementAudience;
use App\Support\SendAnnouncement;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ViewAnnouncement extends ViewRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user() instanceof User && auth()->user()->isUsableAdministrator(), 403);
        parent::authorizeAccess();
    }

    protected function getHeaderActions(): array
    {
        $isDraft = $this->getRecord()->status === Announcement::STATUS_DRAFT;

        return [
            EditAction::make()->visible($isDraft),
            DeleteAction::make()->visible($isDraft)->requiresConfirmation(),
            Action::make('send_announcement')
                ->label(__('admin_announcements.actions.send'))
                ->color('primary')
                ->visible($isDraft)
                ->authorize('update', $this->getRecord())
                ->requiresConfirmation()
                ->modalHeading(__('admin_announcements.confirm.heading'))
                ->modalDescription(fn (): string => __('admin_announcements.confirm.description', [
                    'count' => $this->getRecipientCount(),
                ]).' '.__('admin_announcements.confirm.body'))
                ->modalSubmitActionLabel(__('admin_announcements.actions.send'))
                ->action(function (): void {
                    $actor = auth()->user();
                    Gate::authorize('update', $this->getRecord());
                    abort_unless($actor instanceof User && $actor->isUsableAdministrator(), 403);
                    try {
                        $sent = app(SendAnnouncement::class)->handle($this->getRecord(), $actor);
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title(__('admin_announcements.notifications.failed'))->send();
                        report($exception);

                        return;
                    }
                    if (! $sent) {
                        Notification::make()->warning()->title(__('admin_announcements.status.sent'))->send();

                        return;
                    }
                    Notification::make()->success()->title(__('admin_announcements.notifications.sent'))->send();
                    $this->redirect(AnnouncementResource::getUrl('view', ['record' => $this->getRecord()], false, 'admin'));
                }),
        ];
    }

    private function getRecipientCount(): int
    {
        return app(AnnouncementAudience::class)->draftRecipientQuery($this->getRecord())->count();
    }
}
