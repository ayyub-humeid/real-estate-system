<?php

namespace App\Notifications;

use App\Enums\AcquisitionStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Stores translation keys and resolves them in each recipient's own locale. */
class AcquisitionWorkflowNotification extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $params `status` and `reason` are resolved per recipient locale. */
    public function __construct(
        public string $title,
        public string $body,
        public ?int $acquisitionId = null,
        public array $params = [],
        public ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $locale = in_array($notifiable->locale ?? null, ['ar', 'en'], true) ? $notifiable->locale : config('app.locale', 'ar');

        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            $params = $this->params;
            if (isset($params['status'])) {
                $params['status'] = AcquisitionStatus::labelFor($params['status']);
            }
            if (isset($params['reason'])) {
                $params['reason'] = __($params['reason']);
            }

            $message = \Filament\Notifications\Notification::make()
                ->title(__($this->title, $params))
                ->body(__($this->body, $params));

            if ($this->url) {
                $message->actions([\Filament\Notifications\Actions\Action::make('open')->label(__('Open'))->url($this->url)->markAsRead()]);
            }

            return array_merge($message->getDatabaseMessage(), ['acquisition_id' => $this->acquisitionId]);
        } finally {
            app()->setLocale($previous);
        }
    }
}
