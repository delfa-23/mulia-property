<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\AlertActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AlertNotificationService
{
    public function notifyReceived(Alert $alert): void
    {
        $divisionIds = $alert->recipientDivisions()->pluck('divisions.id');
        $recipients = User::query()
            ->where(function (Builder $query) use ($divisionIds): void {
                $query->where('role', 'admin');

                if ($divisionIds->isNotEmpty()) {
                    $query->orWhereIn('division_id', $divisionIds);
                }
            })
            ->when($alert->created_by !== null, fn (Builder $query) => $query->where('id', '!=', $alert->created_by))
            ->get();

        Notification::send($recipients, new AlertActivity(
            $alert->id,
            'received',
            'Alert baru: '.$alert->title,
            $alert->message,
            route('alerts.index'),
        ));
    }

    public function notifyResolved(Alert $alert, User $resolver): void
    {
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('type', AlertActivity::class)
            ->where('data->alert_id', $alert->id)
            ->where('data->event', 'received')
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        $sender = $alert->createdBy;

        if ($sender === null || $sender->is($resolver)) {
            return;
        }

        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $sender->id)
            ->where('type', AlertActivity::class)
            ->where('data->alert_id', $alert->id)
            ->where('data->event', 'resolved')
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        $sender->notify(new AlertActivity(
            $alert->id,
            'resolved',
            'Alert selesai: '.$alert->title,
            'Alert diselesaikan oleh '.$resolver->name.'.',
            route('alerts.index'),
        ));
    }
}
