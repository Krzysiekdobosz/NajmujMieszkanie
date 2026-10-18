<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Api\ApiController;
use App\Models\Conversation;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends ApiController
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $user = $request->user();

        $conversation = Conversation::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('owner_id', $user->id);
        })->findOrFail($id);

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $request->body,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $recipientId = $conversation->user_id === $user->id ? $conversation->owner_id : $conversation->user_id;
        $this->notifications->newMessage($recipientId, $user->name, $conversation->id);

        return $this->created([
            'id' => $message->id,
            'body' => $message->body,
            'sender' => ['id' => $user->id, 'name' => $user->name],
            'is_mine' => true,
            'is_read' => false,
            'created_at' => $message->created_at?->toIso8601String(),
        ], 'Wiadomość wysłana.');
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $conversation = Conversation::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('owner_id', $user->id);
        })->findOrFail($id);

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return $this->success(null, 'Oznaczono jako przeczytane.');
    }
}
