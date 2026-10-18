<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\ViewingStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Property;
use App\Models\PropertyViewing;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyViewingController extends ApiController
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $viewings = PropertyViewing::where('user_id', $request->user()->id)
            ->with(['property:id,title,slug,city,voivodeship', 'owner:id,name,phone'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('proposed_at')
            ->paginate(15);

        return $this->paginated($viewings, $viewings->getCollection()->map(fn($v) => $this->format($v)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'proposed_at' => ['required', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $property = Property::findOrFail($request->property_id);

        $conflict = PropertyViewing::where('property_id', $property->id)
            ->where('status', ViewingStatus::Accepted->value)
            ->whereBetween('proposed_at', [
                Carbon::parse($request->proposed_at)->subMinutes(59),
                Carbon::parse($request->proposed_at)->addMinutes(59),
            ])->exists();

        if ($conflict) {
            return $this->error('W tym terminie jest już zarezerwowane oglądanie.', 422);
        }

        $viewing = PropertyViewing::create([
            'property_id' => $property->id,
            'user_id' => $request->user()->id,
            'owner_id' => $property->user_id,
            'proposed_at' => $request->proposed_at,
            'note' => $request->note,
            'status' => ViewingStatus::Pending->value,
        ]);

        $this->notifications->newViewing($property->user_id, $request->user()->name, $property->title, $viewing->id);

        return $this->created($this->format($viewing->load(['property:id,title,slug', 'owner:id,name,phone'])), 'Prośba o oglądanie wysłana.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $viewing = PropertyViewing::where('user_id', $request->user()->id)->findOrFail($id);

        if (!in_array($viewing->status, [ViewingStatus::Pending, ViewingStatus::Accepted])) {
            return $this->error('Nie można anulować tej rezerwacji.', 422);
        }

        $viewing->update(['status' => ViewingStatus::Cancelled->value]);

        return $this->success(null, 'Rezerwacja anulowana.');
    }

    private function format(PropertyViewing $v): array
    {
        return [
            'id' => $v->id,
            'property' => $v->property ? ['id' => $v->property->id, 'title' => $v->property->title, 'slug' => $v->property->slug, 'city' => $v->property->city] : null,
            'owner' => $v->owner ? ['id' => $v->owner->id, 'name' => $v->owner->name, 'phone' => $v->owner->phone] : null,
            'proposed_at' => $v->proposed_at?->toIso8601String(),
            'status' => $v->status?->value,
            'status_label' => $v->status?->label(),
            'note' => $v->note,
            'owner_note' => $v->owner_note,
        ];
    }
}
