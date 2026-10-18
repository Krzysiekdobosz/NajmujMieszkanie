<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Api\ApiController;
use App\Models\Conversation;
use App\Models\Property;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends ApiController
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('owner_id', $user->id);
        })
            ->with(['property:id,title,slug', 'user:id,name', 'owner:id,name', 'lastMessage'])
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('is_read', false)->where('sender_id', '!=', $user->id);
            }])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return $this->paginated($conversations, $conversations->getCollection()->map(fn($c) => [
            'id' => $c->id,
            'property' => $c->property ? ['id' => $c->property->id, 'title' => $c->property->title, 'slug' => $c->property->slug] : null,
            'other_user' => $user->id === $c->user_id ? ['id' => $c->owner->id, 'name' => $c->owner->name] : ['id' => $c->user->id, 'name' => $c->user->name],
            'last_message' => $c->lastMessage ? ['body' => $c->lastMessage->body, 'created_at' => $c->lastMessage->created_at?->toIso8601String()] : null,
            'unread_count' => $c->unread_count,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $property = Property::findOrFail($request->property_id);

        if ($property->user_id === $request->user()->id) {
            return $this->error('Nie możesz pisać do siebie.', 422);
        }

        $existing = Conversation::where('property_id', $property->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            $msg = $existing->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $request->message,
            ]);
            $existing->update(['last_message_at' => now()]);
            $this->notifications->newMessage($existing->owner_id, $request->user()->name, $existing->id);
            return $this->created(['conversation_id' => $existing->id], 'Wiadomość wysłana.');
        }

        $conversation = Conversation::create([
            'property_id' => $property->id,
            'user_id' => $request->user()->id,
            'owner_id' => $property->user_id,
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $request->message,
        ]);

        $this->notifications->newMessage($conversation->owner_id, $request->user()->name, $conversation->id);

        return $this->created(['conversation_id' => $conversation->id], 'Wiadomość wysłana.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $conversation = Conversation::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('owner_id', $user->id);
        })
            ->with(['property:id,title,slug', 'user:id,name,email', 'owner:id,name,email', 'messages.sender:id,name'])
            ->findOrFail($id);

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return $this->success([
            'id' => $conversation->id,
            'property' => $conversation->property ? ['id' => $conversation->property->id, 'title' => $conversation->property->title, 'slug' => $conversation->property->slug] : null,
            'other_user' => $user->id === $conversation->user_id ? ['id' => $conversation->owner->id, 'name' => $conversation->owner->name] : ['id' => $conversation->user->id, 'name' => $conversation->user->name],
            'messages' => $conversation->messages->map(fn($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'sender' => ['id' => $m->sender->id, 'name' => $m->sender->name],
                'is_mine' => $m->sender_id === $user->id,
                'is_read' => $m->is_read,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
