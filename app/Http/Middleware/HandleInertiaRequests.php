<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => function () use ($request) {
                $user = $request->user();
                if (!$user) {
                    return [];
                }

                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                        $hasSoftDelete = \Illuminate\Support\Facades\Schema::hasColumn('notifications', 'deleted_at');
                        $dbNotifs = $user->notifications()
                            ->when($hasSoftDelete, fn ($q) => $q->whereNull('deleted_at'))
                            ->latest()
                            ->limit(15)
                            ->get();
                        if ($dbNotifs->isNotEmpty()) {
                            return $dbNotifs->map(function ($n) {
                                $data = is_array($n->data) ? $n->data : [];
                                return [
                                    'id' => $n->id,
                                    'is_db' => true,
                                    'type' => $data['category'] ?? 'system',
                                    'title' => $data['title'] ?? 'Notifikasi Sistem',
                                    'message' => $data['message'] ?? '',
                                    'time' => $n->created_at ? $n->created_at->diffForHumans() : 'Baru saja',
                                    'timestamp' => $n->created_at ? $n->created_at->timestamp : time(),
                                    'is_read' => $n->read_at !== null,
                                    'link' => $data['link'] ?? '/admin/notifikasi',
                                ];
                            })->values()->all();
                        }
                    }
                } catch (\Throwable $e) {
                }

                return [];
            },
            'unread_notifications_count' => function () use ($request) {
                $user = $request->user();
                if (!$user) {
                    return 0;
                }
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                        $hasSoftDelete = \Illuminate\Support\Facades\Schema::hasColumn('notifications', 'deleted_at');
                        return $user->unreadNotifications()
                            ->when($hasSoftDelete, fn ($q) => $q->whereNull('deleted_at'))
                            ->count();
                    }
                } catch (\Throwable $e) {
                }
                return 0;
            },
        ];
    }
}
