<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\ViewingStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\PropertyViewing;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ViewingController extends ApiController
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $viewings = PropertyViewing::where('owner_id', $request->user()->id)
            ->with(['property:id,title,slug,city', 'user:id,name,phone,email'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('proposed_at')
            ->paginate(20);

        return $this->paginated($viewings, $viewings->getCollection()->map(fn($v) => [
            'id' => $v->id,
            'property' => $v->property ? ['id' => $v->property->id, 'title' => $v->property->title, 'slug' => $v->property->slug, 'city' => $v->property->city] : null,
            'user' => $v->user ? ['id' => $v->user->id, 'name' => $v->user->name, 'phone' => $v->user->phone, 'email' => $v->user->email] : null,
            'proposed_at' => $v->proposed_at?->toIso8601String(),
            'status' => $v->status?->value,
            'status_label' => $v->status?->label(),
            'note' => $v->note,
            'owner_note' => $v->owner_note,
        ]));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::enum(ViewingStatus::class)],
            'owner_note' => ['nullable', 'string', 'max:500'],
        ]);

        $viewing = PropertyViewing::where('owner_id', $request->user()->id)
            ->with('property:id,title')
            ->findOrFail($id);

        $viewing->update([
            'status' => $request->status,
            'owner_note' => $request->owner_note,
        ]);

        $title = $viewing->property?->title ?? '';
        match ($viewing->status) {
            ViewingStatus::Accepted => $this->notifications->viewingAccepted($viewing->user_id, $title, $viewing->id),
            ViewingStatus::Rejected => $this->notifications->viewingRejected($viewing->user_id, $title, $viewing->id),
            default => null,
        };

        return $this->success(null, 'Status rezerwacji zaktualizowany.');
    }
}
