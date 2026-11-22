<?php

namespace App\Notifications;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A bell notification for a workflow event. It stores a translation key and its
 * parameters, never finished text, so every recipient reads it in their own language.
 * The key is the base of a `{key}_title` / `{key}_body` pair.
 */
class WorkflowNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $params  `status`, `from`, `to` and `role` hold codes and are shown as words.
     * @param  array<string, mixed>  $context  Extra ids kept with the record (e.g. reservation_id), used to avoid sending twice.
     */
    public function __construct(
        public string $event,
        public array $params = [],
        public ?string $url = null,
        public ?int $projectId = null,
        public array $context = [],
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
            foreach (['status', 'from', 'to'] as $key) {
                if (isset($params[$key])) {
                    $params[$key] = status_label((string) $params[$key]);
                }
            }
            if (isset($params['role'])) {
                $roleKey = 'project.member_roles.'.str((string) $params['role'])->lower()->replace([' ', '-'], '_')->toString();
                $params['role'] = \Illuminate\Support\Facades\Lang::has($roleKey) ? __($roleKey) : str((string) $params['role'])->headline()->toString();
            }

            $message = FilamentNotification::make()
                ->title(__($this->event.'_title', $params))
                ->body(__($this->event.'_body', $params));

            if ($this->url) {
                $message->actions([Action::make('open')->label(__('Open'))->url($this->url)->markAsRead()]);
            }

            return array_merge($message->getDatabaseMessage(), array_filter([
                'event' => $this->event,
                'project_id' => $this->projectId,
            ]), $this->context);
        } finally {
            app()->setLocale($previous);
        }
    }
}
