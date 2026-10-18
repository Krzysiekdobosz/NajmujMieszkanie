<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PropertyController extends ApiController
{
    public function __construct(private ActivityLogService $log)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $properties = Property::where('user_id', $request->user()->id)
            ->with(['images', 'mainImage'])
            ->withCount(['favorites', 'viewings', 'conversations'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(min((int) $request->input('per_page', 15), 100));

        return $this->paginated($properties, PropertyResource::collection($properties));
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['status'] = PropertyStatus::Draft->value;

        $property = Property::create($data);

        $this->log->log('property.create', $request->user()->id,
            "Utworzono ofertę: {$property->title}", Property::class, $property->id, $request);

        return $this->created(new PropertyResource($property->load(['images', 'mainImage'])), 'Oferta została utworzona.');
    }

    public function show(int $id): JsonResponse
    {
        $property = Property::where('user_id', request()->user()->id)
            ->with(['images', 'mainImage', 'owner'])
            ->findOrFail($id);

        return $this->success(new PropertyResource($property));
    }

    public function update(UpdatePropertyRequest $request, int $id): JsonResponse
    {
        $property = Property::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validated();

        if (isset($data['status']) && $data['status'] === PropertyStatus::Pending->value) {
            $data['published_at'] = null;
            $data['rejected_reason'] = null;
        }

        $property->update($data);

        $this->log->log('property.update', $request->user()->id,
            "Zaktualizowano ofertę: {$property->title}", Property::class, $property->id, $request);

        return $this->success(new PropertyResource($property->fresh(['images', 'mainImage'])), 'Oferta została zaktualizowana.');
    }

    public function destroy(int $id): JsonResponse
    {
        $property = Property::where('user_id', request()->user()->id)->findOrFail($id);
        $this->log->log('property.delete', request()->user()->id,
            "Usunięto ofertę: {$property->title}", Property::class, $property->id, request());
        $property->delete();

        return $this->noContent();
    }

    public function publish(int $id): JsonResponse
    {
        $property = Property::where('user_id', request()->user()->id)->findOrFail($id);
        if (!in_array($property->status, [PropertyStatus::Draft, PropertyStatus::Rejected])) {
            return $this->error('Nie można opublikować oferty o tym statusie.', 422);
        }
        $property->update(['status' => PropertyStatus::Pending->value]);

        return $this->success(new PropertyResource($property->fresh()), 'Oferta wysłana do weryfikacji.');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;
        while (Property::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }
}
