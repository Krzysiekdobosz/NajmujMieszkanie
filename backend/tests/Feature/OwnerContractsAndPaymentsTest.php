<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PropertySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerContractsAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $tenant;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->seed([RoleSeeder::class, AdminUserSeeder::class, PropertySeeder::class]);

        $this->owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->tenant = User::where('email', 'user@example.com')->firstOrFail();
        $this->property = Property::where('user_id', $this->owner->id)->firstOrFail();
    }

    public function test_owner_generates_contract_with_pdf_and_tenant_is_notified(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/owner/contracts', [
            'property_id' => $this->property->id,
            'tenant_email' => $this->tenant->email,
            'start_date' => '2030-01-01',
            'rental_months' => 12,
            'owner_name' => 'Jan Właściciel',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'generated')
            ->assertJsonPath('data.end_date', '2030-12-31')
            ->assertJsonPath('data.tenant.email', $this->tenant->email);

        $this->getJson('/api/v1/owner/contracts/' . $response->json('data.id') . '/download')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertTrue(Notification::where('user_id', $this->tenant->id)->where('type', 'contract_generated')->exists());
    }

    public function test_contract_requires_existing_tenant_account(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/owner/contracts', [
            'property_id' => $this->property->id,
            'tenant_email' => 'nobody@example.com',
            'start_date' => '2030-01-01',
            'owner_name' => 'Jan Właściciel',
        ])->assertUnprocessable()->assertJsonValidationErrors('tenant_email');
    }

    public function test_owner_adds_payment_and_marks_it_as_paid(): void
    {
        Sanctum::actingAs($this->owner);

        $id = $this->postJson('/api/v1/owner/payments', [
            'property_id' => $this->property->id,
            'tenant_email' => $this->tenant->email,
            'type' => 'rent',
            'amount' => 3200,
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/owner/payments')
            ->assertOk()
            ->assertJsonPath('summary.pending', 3200);

        $this->patchJson("/api/v1/owner/payments/{$id}/status", ['status' => 'paid'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->getJson('/api/v1/owner/payments')->assertJsonPath('summary.pending', 0);
    }

    public function test_regular_user_cannot_access_owner_panel(): void
    {
        Sanctum::actingAs($this->tenant);

        $this->getJson('/api/v1/owner/contracts')->assertForbidden();
        $this->getJson('/api/v1/owner/payments')->assertForbidden();
    }

    public function test_notifications_can_be_listed_and_marked_as_read(): void
    {
        Notification::create(['user_id' => $this->tenant->id, 'type' => 'test', 'title' => 'Test', 'body' => 'Treść', 'data' => []]);
        Sanctum::actingAs($this->tenant);

        $id = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.is_read', false)
            ->json('data.0.id');

        $this->patchJson("/api/v1/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/v1/notifications')->assertJsonPath('data.0.is_read', true);
    }

    public function test_viewing_request_notifies_owner(): void
    {
        Sanctum::actingAs($this->tenant);

        $this->postJson('/api/v1/property-viewings', [
            'property_id' => $this->property->id,
            'proposed_at' => now()->addDays(3)->setTime(17, 0)->toIso8601String(),
        ])->assertCreated();

        $this->assertTrue(Notification::where('user_id', $this->owner->id)->where('type', 'new_viewing')->exists());
    }
}
