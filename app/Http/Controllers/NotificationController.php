<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    public function index(
        Request $request,
    ): Response {
        $user =
            $request->user();

        $notifications =
            Notification::query()
                ->where(
                    'recipient_user_id',
                    $user->id
                )
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(
                    static function (
                        Notification $notification
                    ): array {
                        return [
                            'id' =>
                                $notification->id,

                            'type' =>
                                $notification
                                    ->notification_type
                                    ->value,

                            'priority' =>
                                $notification
                                    ->priority
                                    ->value,

                            'title' =>
                                $notification->title,

                            'message' =>
                                $notification->message,

                            'action_url' =>
                                $notification
                                    ->action_url,

                            'is_read' =>
                                $notification
                                    ->isRead(),

                            'read_at' =>
                                $notification
                                    ->read_at
                                    ?->toIso8601String(),

                            'created_at' =>
                                $notification
                                    ->created_at
                                    ?->toIso8601String(),
                        ];
                    }
                );

        return Inertia::render(
            'Notifications/Index',
            [
                'notifications' =>
                    $notifications,
            ]
        );
    }

    public function markRead(
        Request $request,
        Notification $notification,
        NotificationService $notificationService,
    ): RedirectResponse {
        $notificationService
            ->markRead(
                $request->user(),
                $notification
            );

        return back();
    }
}