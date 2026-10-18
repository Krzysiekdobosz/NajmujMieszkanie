<?php

namespace Database\Seeders;

use App\Enums\ContactMessageStatus;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PropertyStatus;
use App\Enums\ViewingStatus;
use App\Models\ContactMessage;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyViewing;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample interactions between the default accounts, so every panel
 * (user, owner, admin) has something to show right after seeding.
 */
class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'owner@example.com')->first();
        $tenant = User::where('email', 'user@example.com')->first();
        if (!$owner || !$tenant) {
            return;
        }

        $properties = Property::where('user_id', $owner->id)
            ->where('status', PropertyStatus::Published)
            ->orderBy('id')
            ->get();
        if ($properties->count() < 4) {
            return;
        }

        [$first, $second, $third, $fourth] = $properties->take(4)->all();

        foreach ([$first, $third, $fourth] as $property) {
            Favorite::firstOrCreate(['user_id' => $tenant->id, 'property_id' => $property->id]);
        }

        // Oglądania w różnych statusach
        $viewings = [
            [$first, now()->addDays(2)->setTime(17, 0), ViewingStatus::Pending, 'Czy mogę przyjść z partnerem?', null],
            [$second, now()->addDays(4)->setTime(18, 30), ViewingStatus::Accepted, null, 'Zapraszam, kod do domofonu wyślę SMS-em.'],
            [$third, now()->subDays(3)->setTime(16, 0), ViewingStatus::Completed, 'Interesuje mnie najem od przyszłego miesiąca.', null],
        ];
        foreach ($viewings as [$property, $date, $status, $note, $ownerNote]) {
            PropertyViewing::firstOrCreate(
                ['property_id' => $property->id, 'user_id' => $tenant->id],
                ['owner_id' => $owner->id, 'proposed_at' => $date, 'status' => $status, 'note' => $note, 'owner_note' => $ownerNote]
            );
        }

        // Rozmowa o mieszkaniu
        $conversation = Conversation::firstOrCreate(
            ['property_id' => $first->id, 'user_id' => $tenant->id, 'owner_id' => $owner->id],
            ['last_message_at' => now()->subHours(2)]
        );
        if ($conversation->messages()->doesntExist()) {
            $thread = [
                [$tenant, 'Dzień dobry, czy mieszkanie jest nadal dostępne od przyszłego miesiąca?', 26, true],
                [$owner, 'Dzień dobry, tak – mieszkanie jest dostępne. Zapraszam na oglądanie w tym tygodniu.', 24, true],
                [$tenant, 'Świetnie. Czy w cenie jest miejsce w garażu?', 3, true],
                [$owner, 'Tak, miejsce postojowe jest wliczone w czynsz.', 2, false],
            ];
            foreach ($thread as [$sender, $body, $hoursAgo, $read]) {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'body' => $body,
                    'is_read' => $read,
                    'read_at' => $read ? now()->subHours($hoursAgo - 1) : null,
                    'created_at' => now()->subHours($hoursAgo),
                    'updated_at' => now()->subHours($hoursAgo),
                ]);
            }
        }

        // Umowa i płatności dla zakończonego oglądania
        $contract = Contract::firstOrCreate(
            ['property_id' => $third->id, 'tenant_id' => $tenant->id],
            [
                'owner_id' => $owner->id,
                'status' => ContractStatus::Signed,
                'owner_name' => $owner->name,
                'owner_address' => 'ul. Przykładowa 1, 00-001 Warszawa',
                'tenant_name' => $tenant->name,
                'tenant_address' => 'ul. Studencka 5, 20-001 Lublin',
                'property_address' => trim("{$third->street}, {$third->postal_code} {$third->city}", ', '),
                'property_type' => $third->property_type?->value,
                'rental_price' => $third->price,
                'deposit_amount' => $third->deposit,
                'admin_fee' => $third->admin_fee,
                'start_date' => now()->startOfMonth(),
                'end_date' => now()->startOfMonth()->addMonths(12)->subDay(),
                'rental_months' => 12,
                'conditions' => 'Najemca zobowiązuje się do opłacania czynszu do 10. dnia każdego miesiąca.',
                'generated_at' => now()->subDays(7),
            ]
        );

        if ($contract->payments()->doesntExist()) {
            $payments = [
                [PaymentType::Deposit, $third->deposit, 'Kaucja zwrotna', now()->startOfMonth(), PaymentStatus::Paid],
                [PaymentType::Rent, $third->price, 'Czynsz – bieżący miesiąc', now()->startOfMonth()->addDays(9), PaymentStatus::Paid],
                [PaymentType::Rent, $third->price, 'Czynsz – następny miesiąc', now()->startOfMonth()->addMonth()->addDays(9), PaymentStatus::Pending],
                [PaymentType::ExtraFee, 180, 'Rozliczenie mediów', now()->subDays(3), PaymentStatus::Pending],
            ];
            foreach ($payments as [$type, $amount, $description, $due, $status]) {
                Payment::create([
                    'property_id' => $third->id,
                    'owner_id' => $owner->id,
                    'tenant_id' => $tenant->id,
                    'contract_id' => $contract->id,
                    'type' => $type,
                    'status' => $status,
                    'amount' => $amount ?? 0,
                    'description' => $description,
                    'due_date' => $due,
                    'paid_at' => $status === PaymentStatus::Paid ? $due : null,
                ]);
            }
        }

        // Powiadomienia
        if (Notification::where('user_id', $tenant->id)->doesntExist()) {
            Notification::create(['user_id' => $tenant->id, 'type' => 'viewing_accepted', 'title' => 'Rezerwacja zaakceptowana', 'body' => "Twoja rezerwacja oglądania \"{$second->title}\" została zaakceptowana.", 'data' => []]);
            Notification::create(['user_id' => $tenant->id, 'type' => 'new_message', 'title' => 'Nowa wiadomość', 'body' => "Masz nową wiadomość od {$owner->name}.", 'data' => ['conversation_id' => $conversation->id]]);
        }
        if (Notification::where('user_id', $owner->id)->doesntExist()) {
            Notification::create(['user_id' => $owner->id, 'type' => 'new_viewing', 'title' => 'Nowa rezerwacja oglądania', 'body' => "{$tenant->name} chce zobaczyć \"{$first->title}\".", 'data' => []]);
        }

        // Zgłoszenia z formularza kontaktowego
        if (ContactMessage::doesntExist()) {
            ContactMessage::create(['name' => 'Marek Zieliński', 'email' => 'marek@example.com', 'subject' => 'Pytanie o weryfikację ofert', 'body' => 'Czy oferty są sprawdzane przed publikacją?', 'status' => ContactMessageStatus::New]);
            ContactMessage::create(['user_id' => $tenant->id, 'name' => $tenant->name, 'email' => $tenant->email, 'subject' => 'Problem z dodaniem zdjęcia profilowego', 'body' => 'Przy zapisie profilu pojawia się błąd.', 'status' => ContactMessageStatus::InProgress, 'admin_note' => 'Przekazane do zespołu technicznego.']);
        }
    }
}
