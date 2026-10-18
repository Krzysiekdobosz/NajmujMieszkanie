<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyController extends ApiController
{
    public function __construct(private ActivityLogService $log)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $properties = Property::with(['owner:id,name,email', 'images'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->where(fn($w) => $w->where('title', 'like', "%$s%")->orWhere('city', 'like', "%$s%")))
            ->when($request->type, fn($q, $t) => $q->where('property_type', $t))
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->paginated($properties, PropertyResource::collection($properties));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::enum(PropertyStatus::class)],
            'rejected_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $property = Property::findOrFail($id);
        $data = ['status' => $request->status];

        if ($request->status === PropertyStatus::Published->value) {
            $data['published_at'] = now();
            $data['rejected_reason'] = null;
        }

        if ($request->status === PropertyStatus::Rejected->value) {
            $data['rejected_reason'] = $request->rejected_reason;
            $data['published_at'] = null;
        }

        $property->update($data);

        $this->log->log('admin.property.status', $request->user()->id,
            "Zmieniono status oferty #{$property->id} na {$request->status}",
            Property::class, $property->id, $request);

        return $this->success(null, 'Status oferty zaktualizowany.');
    }
}
