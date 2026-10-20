<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Notifications\AlertActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertNotificationController extends Controller
{
    public function pendingCount(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->hasValidDivisionAssignment()) {
            return response()->json(['count' => 0]);
        }

        $activeAlertCount = Alert::query()
            ->where('is_resolved', false)
            ->where(function (Builder $query) use ($user): void {
                $query->whereNull('created_by')
                    ->orWhere('created_by', '!=', $user->id);
            })
            ->when(
                ! $user->isAdmin(),
                fn (Builder $query) => $query->whereHas(
                    'recipientDivisions',
                    fn (Builder $divisions) => $divisions->whereKey($user->division_id),
                ),
            )
            ->count();
        $unreadResolvedCount = $user
            ->unreadNotifications()
            ->where('type', AlertActivity::class)
            ->where('data->event', 'resolved')
            ->count();

        return response()->json(['count' => $activeAlertCount + $unreadResolvedCount]);
    }
}
