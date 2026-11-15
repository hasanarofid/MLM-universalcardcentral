<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $notifications = [];

        if (Schema::hasTable('notifications')) {
            $hasSoftDelete = Schema::hasColumn('notifications', 'deleted_at');
            $notifications = $user->notifications()
                ->when($hasSoftDelete, fn ($q) => $q->whereNull('deleted_at'))
                ->latest()
                ->limit(100)
                ->get()
                ->map(function ($n) {
                    $data = is_array($n->data) ? $n->data : [];
                    return [
                        'id' => $n->id,
                        'type' => $data['category'] ?? 'system',
                        'title' => $data['title'] ?? 'Notifikasi Sistem',
                        'message' => $data['message'] ?? '',
                        'link' => $data['link'] ?? '/admin/dashboard',
                        'is_read' => $n->read_at !== null,
                        'time' => $n->created_at ? $n->created_at->diffForHumans() : 'Baru saja',
                        'created_at_formatted' => $n->created_at ? $n->created_at->format('d M Y, H:i') : '',
                        'timestamp' => $n->created_at ? $n->created_at->timestamp : time(),
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('Admin/Notifications', [
            'allNotifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification && $notification->read_at === null) {
            $notification->markAsRead();
        }

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi telah ditandai dibaca.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            if (Schema::hasColumn('notifications', 'deleted_at')) {
                $notification->update(['deleted_at' => now()]);
            } else {
                $notification->delete();
            }
        }

        return back()->with('success', 'Notifikasi berhasil dihapus.');
    }
}
