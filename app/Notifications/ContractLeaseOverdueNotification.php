<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells a manager that an active lease is past its end date. It never ends the
 * contract: the tenant may be renewing, so a person decides. Text is resolved in
 * each recipient's locale.
 */
class ContractLeaseOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $contractId,
        public string $contractNumber,
        public string $unitNumber,
        public string $endDate,
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
            return [
                'title' => __('contract_domain.end.overdue_title'),
                'body' => __('contract_domain.end.overdue_body', ['contract' => $this->contractNumber, 'unit' => $this->unitNumber, 'date' => $this->endDate]),
                'contract_id' => $this->contractId,
            ];
        } finally {
            app()->setLocale($previous);
        }
    }
}
