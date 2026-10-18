<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Api\ApiController;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends ApiController
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $ownerId = $request->user()->id;

        $payments = Payment::where('owner_id', $ownerId)
            ->with(['property:id,title,slug,city', 'tenant:id,name,email'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->orderByRaw('due_date IS NULL, due_date DESC')
            ->paginate(20);

        $summary = [
            'pending' => (float) Payment::where('owner_id', $ownerId)->where('status', PaymentStatus::Pending)->sum('amount'),
            'paid_this_month' => (float) Payment::where('owner_id', $ownerId)
                ->where('status', PaymentStatus::Paid)
                ->where('paid_at', '>=', now()->startOfMonth())
                ->sum('amount'),
            'overdue_count' => Payment::where('owner_id', $ownerId)
                ->where('status', PaymentStatus::Pending)
                ->whereDate('due_date', '<', today())
                ->count(),
        ];

        $response = $this->paginated($payments, $payments->getCollection()->map(fn($p) => $this->format($p)));
        $payload = $response->getData(true);
        $payload['summary'] = $summary;

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'tenant_email' => ['required', 'email', Rule::exists('users', 'email')],
            'contract_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::enum(PaymentType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
        ], [
            'tenant_email.exists' => 'Najemca musi mieć konto w serwisie.',
        ]);

        $ownerId = $request->user()->id;
        $property = Property::where('user_id', $ownerId)->find($data['property_id']);
        if (!$property) {
            return $this->notFound('Nie znaleziono oferty.');
        }

        if (!empty($data['contract_id']) && !Contract::where('owner_id', $ownerId)->whereKey($data['contract_id'])->exists()) {
            return $this->notFound('Nie znaleziono umowy.');
        }

        $tenant = User::where('email', $data['tenant_email'])->first();

        $payment = Payment::create([
            'property_id' => $property->id,
            'owner_id' => $ownerId,
            'tenant_id' => $tenant->id,
            'contract_id' => $data['contract_id'] ?? null,
            'type' => $data['type'],
            'status' => PaymentStatus::Pending,
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'],
        ]);

        $this->notifications->send(
            $tenant->id,
            'payment_requested',
            'Nowa płatność do opłacenia',
            "Wynajmujący dodał płatność {$payment->amount} zł za \"{$property->title}\".",
            ['payment_id' => $payment->id]
        );

        return $this->created($this->format($payment->load(['property:id,title,slug,city', 'tenant:id,name,email'])), 'Płatność została dodana.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $payment = Payment::where('owner_id', $request->user()->id)
            ->with(['property:id,title,slug,city', 'tenant:id,name,email'])
            ->findOrFail($id);

        return $this->success($this->format($payment));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', Rule::enum(PaymentStatus::class)]]);

        $payment = Payment::where('owner_id', $request->user()->id)->findOrFail($id);
        $status = PaymentStatus::from($request->status);

        $payment->update([
            'status' => $status,
            'paid_at' => $status === PaymentStatus::Paid ? ($payment->paid_at ?? now()) : null,
        ]);

        return $this->success($this->format($payment->load(['property:id,title,slug,city', 'tenant:id,name,email'])), 'Status płatności zaktualizowany.');
    }

    private function format(Payment $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type?->value,
            'type_label' => $p->type?->label(),
            'status' => $p->status?->value,
            'status_label' => $p->status?->label(),
            'amount' => $p->amount,
            'description' => $p->description,
            'due_date' => $p->due_date?->format('Y-m-d'),
            'paid_at' => $p->paid_at?->toIso8601String(),
            'is_overdue' => $p->status === PaymentStatus::Pending && $p->due_date?->isPast() && !$p->due_date->isToday(),
            'contract_id' => $p->contract_id,
            'property' => $p->property ? ['id' => $p->property->id, 'title' => $p->property->title, 'slug' => $p->property->slug, 'city' => $p->property->city] : null,
            'tenant' => $p->tenant ? ['id' => $p->tenant->id, 'name' => $p->tenant->name, 'email' => $p->tenant->email] : null,
        ];
    }
}
