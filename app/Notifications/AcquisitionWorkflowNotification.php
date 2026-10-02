<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable; use Illuminate\Notifications\Notification;
class AcquisitionWorkflowNotification extends Notification { use Queueable; public function __construct(public string $title, public string $body, public ?int $acquisitionId=null) {} public function via(object $notifiable): array{return ['database'];} public function toArray(object $notifiable):array{return ['title'=>$this->title,'body'=>$this->body,'acquisition_id'=>$this->acquisitionId];} }
