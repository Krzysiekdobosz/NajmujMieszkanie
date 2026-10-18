<?php

namespace Database\Seeders;

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $categories = collect([
            ['name' => 'Porady dla najemców', 'description' => 'Jak bezpiecznie znaleźć i wynająć mieszkanie.'],
            ['name' => 'Porady dla właścicieli', 'description' => 'Wynajem krok po kroku z perspektywy właściciela.'],
            ['name' => 'Rynek najmu', 'description' => 'Ceny, trendy i statystyki z rynku najmu.'],
        ])->mapWithKeys(fn($c) => [
            $c['name'] => BlogCategory::firstOrCreate(['slug' => Str::slug($c['name'])], $c),
        ]);

        $posts = [
            [
                'category' => 'Porady dla najemców',
                'title' => 'Na co zwrócić uwagę podczas oglądania mieszkania',
                'excerpt' => 'Lista kontrolna, dzięki której nie przeoczysz usterek i ukrytych kosztów.',
                'content' => "Oglądanie mieszkania to najlepszy moment, żeby zadać pytania i sprawdzić stan lokalu.\n\nSprawdź okna, ciśnienie wody, działanie ogrzewania i stan sprzętów AGD. Zapytaj o wysokość opłat administracyjnych oraz średnie rachunki za media z ostatnich miesięcy.\n\nZwróć uwagę na okolicę: komunikację, sklepy i poziom hałasu wieczorem. Jeśli to możliwe, odwiedź mieszkanie o różnych porach dnia.\n\nPrzed podpisaniem umowy zrób zdjęcia stanu mieszkania i dołącz je do protokołu zdawczo-odbiorczego.",
                'tags' => ['oglądanie', 'najem', 'checklista'],
                'days_ago' => 2,
            ],
            [
                'category' => 'Porady dla właścicieli',
                'title' => 'Jak przygotować skuteczne ogłoszenie wynajmu',
                'excerpt' => 'Dobre zdjęcia, konkretny opis i realna cena – trzy filary szybkiego wynajmu.',
                'content' => "Pierwsze wrażenie robią zdjęcia. Zadbaj o dzienne światło, porządek i ujęcia z narożników pokoju.\n\nW opisie podaj konkrety: metraż, piętro, wysokość czynszu administracyjnego, dostępność od kiedy i zasady dotyczące zwierząt.\n\nSprawdź ceny podobnych ofert w okolicy. Mieszkanie wycenione realnie znajduje najemcę średnio dwa razy szybciej.",
                'tags' => ['ogłoszenie', 'właściciel'],
                'days_ago' => 6,
            ],
            [
                'category' => 'Rynek najmu',
                'title' => 'Ceny najmu w największych miastach – jesień 2026',
                'excerpt' => 'Porównanie średnich stawek za kawalerki i mieszkania dwupokojowe.',
                'content' => "Warszawa pozostaje najdroższym rynkiem najmu w Polsce, a tuż za nią plasują się Kraków, Gdańsk i Wrocław.\n\nKawalerki w centrach miast kosztują średnio od 2 100 zł w Łodzi do 2 800 zł w Warszawie. Mieszkania dwupokojowe to wydatek rzędu 2 600–3 600 zł miesięcznie.\n\nNajwiększy popyt notujemy tradycyjnie we wrześniu i październiku, kiedy na rynek wracają studenci.",
                'tags' => ['ceny', 'statystyki'],
                'days_ago' => 10,
            ],
            [
                'category' => 'Porady dla najemców',
                'title' => 'Kaucja zwrotna – co warto o niej wiedzieć',
                'excerpt' => 'Ile wynosi, kiedy musi zostać zwrócona i co może z niej potrącić właściciel.',
                'content' => "Kaucja zabezpiecza właściciela na wypadek zniszczeń lub zaległości w opłatach. Najczęściej wynosi równowartość jednego lub dwóch czynszów.\n\nPo zakończeniu najmu kaucja powinna zostać zwrócona w terminie określonym w umowie. Właściciel może potrącić z niej koszty napraw szkód wykraczających poza normalne zużycie.\n\nProtokół zdawczo-odbiorczy ze zdjęciami to najlepszy sposób na uniknięcie sporów.",
                'tags' => ['kaucja', 'umowa'],
                'days_ago' => 15,
            ],
        ];

        foreach ($posts as $data) {
            $post = BlogPost::updateOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'user_id' => $admin?->id,
                    'category_id' => $categories[$data['category']]->id,
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'content' => $data['content'],
                    'status' => BlogPostStatus::Published,
                    'published_at' => now()->subDays($data['days_ago']),
                ]
            );

            $tagIds = collect($data['tags'])->map(
                fn($name) => BlogTag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id
            );
            $post->tags()->sync($tagIds);
        }
    }
}
