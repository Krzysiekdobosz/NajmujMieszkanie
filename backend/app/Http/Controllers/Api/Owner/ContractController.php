<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\ContractStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Contract;
use App\Models\Property;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends ApiController
{
    public function __construct(
        private PdfService $pdf,
        private NotificationService $notifications,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $contracts = Contract::where('owner_id', $request->user()->id)
            ->with(['property:id,title,slug,city', 'tenant:id,name,email'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return $this->paginated($contracts, $contracts->getCollection()->map(fn($c) => $this->format($c)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'tenant_email' => ['required', 'email', Rule::exists('users', 'email')],
            'start_date' => ['required', 'date'],
            'rental_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_address' => ['nullable', 'string', 'max:500'],
            'owner_id_number' => ['nullable', 'string', 'max:50'],
            'tenant_name' => ['nullable', 'string', 'max:255'],
            'tenant_address' => ['nullable', 'string', 'max:500'],
            'tenant_id_number' => ['nullable', 'string', 'max:50'],
            'conditions' => ['nullable', 'string', 'max:5000'],
        ], [
            'tenant_email.exists' => 'Najemca musi mieć konto w serwisie.',
        ]);

        $property = Property::where('user_id', $request->user()->id)->find($data['property_id']);
        if (!$property) {
            return $this->notFound('Nie znaleziono oferty.');
        }

        $tenant = User::where('email', $data['tenant_email'])->first();
        if ($tenant->id === $request->user()->id) {
            return $this->error('Nie możesz zawrzeć umowy sam ze sobą.', 422);
        }

        $start = Carbon::parse($data['start_date']);
        $months = $data['rental_months'] ?? null;

        $contract = Contract::create([
            'property_id' => $property->id,
            'owner_id' => $request->user()->id,
            'tenant_id' => $tenant->id,
            'status' => ContractStatus::Draft,
            'owner_name' => $data['owner_name'],
            'owner_address' => $data['owner_address'] ?? null,
            'owner_id_number' => $data['owner_id_number'] ?? null,
            'tenant_name' => $data['tenant_name'] ?? $tenant->name,
            'tenant_address' => $data['tenant_address'] ?? null,
            'tenant_id_number' => $data['tenant_id_number'] ?? null,
            'property_address' => trim("{$property->street}, {$property->postal_code} {$property->city}", ', '),
            'property_type' => $property->property_type?->value,
            'rental_price' => $property->price,
            'deposit_amount' => $property->deposit,
            'admin_fee' => $property->admin_fee,
            'start_date' => $start,
            'end_date' => $months ? $start->copy()->addMonths($months)->subDay() : null,
            'rental_months' => $months,
            'conditions' => $data['conditions'] ?? null,
        ]);

        $contract->update([
            'pdf_path' => $this->pdf->generateContract($contract),
            'status' => ContractStatus::Generated,
            'generated_at' => now(),
        ]);

        $this->notifications->send(
            $tenant->id,
            'contract_generated',
            'Nowa umowa najmu',
            "Wynajmujący przygotował umowę najmu dla \"{$property->title}\".",
            ['contract_id' => $contract->id]
        );

        return $this->created($this->format($contract->load(['property:id,title,slug,city', 'tenant:id,name,email'])), 'Umowa została wygenerowana.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $contract = Contract::where('owner_id', $request->user()->id)
            ->with(['property:id,title,slug,city', 'tenant:id,name,email'])
            ->findOrFail($id);

        return $this->success($this->format($contract, true));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', Rule::enum(ContractStatus::class)]]);

        $contract = Contract::where('owner_id', $request->user()->id)->findOrFail($id);
        $contract->update(['status' => $request->status]);

        return $this->success($this->format($contract->load(['property:id,title,slug,city', 'tenant:id,name,email'])), 'Status umowy zaktualizowany.');
    }

    public function download(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $contract = Contract::where('owner_id', $request->user()->id)->findOrFail($id);

        if (!$contract->pdf_path || !Storage::disk('local')->exists($contract->pdf_path)) {
            $contract->update(['pdf_path' => $this->pdf->generateContract($contract), 'generated_at' => now()]);
        }

        return Storage::disk('local')->download($contract->pdf_path, "umowa-najmu-{$contract->id}.pdf");
    }

    private function format(Contract $c, bool $details = false): array
    {
        $data = [
            'id' => $c->id,
            'status' => $c->status?->value,
            'status_label' => $c->status?->label(),
            'property' => $c->property ? ['id' => $c->property->id, 'title' => $c->property->title, 'slug' => $c->property->slug, 'city' => $c->property->city] : null,
            'tenant' => $c->tenant ? ['id' => $c->tenant->id, 'name' => $c->tenant->name, 'email' => $c->tenant->email] : null,
            'rental_price' => $c->rental_price,
            'deposit_amount' => $c->deposit_amount,
            'admin_fee' => $c->admin_fee,
            'start_date' => $c->start_date?->format('Y-m-d'),
            'end_date' => $c->end_date?->format('Y-m-d'),
            'rental_months' => $c->rental_months,
            'generated_at' => $c->generated_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
        ];

        if ($details) {
            $data += $c->only([
                'owner_name', 'owner_address', 'owner_id_number',
                'tenant_name', 'tenant_address', 'tenant_id_number',
                'property_address', 'property_type', 'conditions',
            ]);
        }

        return $data;
    }
}
