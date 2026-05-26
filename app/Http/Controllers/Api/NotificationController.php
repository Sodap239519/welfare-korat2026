<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** GET /api/notifications?per_page=20 */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = max(10, min((int) $request->input('per_page', 20), 100));
        $page = $user->notifications()->paginate($perPage);

        $page->getCollection()->transform(fn ($n) => [
            'id'        => $n->id,
            'data'      => $n->data,
            'read_at'   => $n->read_at?->toIso8601String(),
            'created_at'=> $n->created_at->toIso8601String(),
        ]);

        return response()->json($page);
    }

    /** GET /api/notifications/unread-count */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => (int) $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** POST /api/notifications/{id}/read */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $n = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $n->markAsRead();
        return response()->json(['message' => 'ok']);
    }

    /** POST /api/notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'ok']);
    }
}
