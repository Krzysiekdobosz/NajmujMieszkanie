<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'title' => 'O nas',
                'slug' => 'o-nas',
                'meta_description' => 'NajmujMieszkanie łączy właścicieli i najemców mieszkań oraz pokoi w całej Polsce.',
                'content' => <<<'HTML'
<p>NajmujMieszkanie to serwis, który łączy właścicieli mieszkań i pokoi z osobami szukającymi lokum na dłużej. Chcemy, żeby wynajem był prosty, przejrzysty i bezpieczny dla obu stron.</p>
<h2>Co nas wyróżnia</h2>
<ul>
  <li><strong>Sprawdzone ogłoszenia</strong> – każda oferta przechodzi moderację przed publikacją.</li>
  <li><strong>Wszystko w jednym miejscu</strong> – wiadomości, rezerwacje oglądania, umowy i płatności.</li>
  <li><strong>Jasne warunki</strong> – czynsz, opłaty administracyjne i kaucja widoczne od razu.</li>
</ul>
<p>Serwis powstał jako projekt zaliczeniowy, ale został zbudowany tak, jak prawdziwa aplikacja produkcyjna.</p>
HTML,
            ],
            [
                'title' => 'Jak to działa',
                'slug' => 'jak-to-dziala',
                'meta_description' => 'Wynajem mieszkania lub pokoju w czterech prostych krokach.',
                'content' => <<<'HTML'
<h2>Dla najemców</h2>
<ol>
  <li>Przeglądaj oferty i filtruj je według miasta, ceny, metrażu i udogodnień.</li>
  <li>Dodaj interesujące ogłoszenia do ulubionych.</li>
  <li>Napisz do właściciela lub od razu zarezerwuj termin oglądania.</li>
  <li>Po oglądaniu podpisz umowę i opłacaj czynsz – historię płatności znajdziesz w panelu.</li>
</ol>
<h2>Dla właścicieli</h2>
<ol>
  <li>Załóż konto właściciela i dodaj ofertę ze zdjęciami.</li>
  <li>Po akceptacji moderatora ogłoszenie pojawi się w wynikach wyszukiwania.</li>
  <li>Odpowiadaj na wiadomości i potwierdzaj terminy oglądania.</li>
  <li>Wygeneruj umowę najmu w PDF i śledź płatności najemców.</li>
</ol>
HTML,
            ],
            [
                'title' => 'Kontakt',
                'slug' => 'kontakt',
                'meta_description' => 'Skontaktuj się z zespołem NajmujMieszkanie.',
                'content' => <<<'HTML'
<p>Masz pytanie dotyczące ogłoszenia, konta lub współpracy? Napisz do nas przez formularz poniżej – odpowiadamy zwykle w ciągu jednego dnia roboczego.</p>
<p><strong>E-mail:</strong> kontakt@najmujmieszkanie.example<br><strong>Godziny pracy:</strong> poniedziałek–piątek, 9:00–17:00</p>
HTML,
            ],
            [
                'title' => 'FAQ',
                'slug' => 'faq',
                'meta_description' => 'Najczęściej zadawane pytania o wynajem w serwisie NajmujMieszkanie.',
                'content' => <<<'HTML'
<h3>Czy korzystanie z serwisu jest płatne?</h3>
<p>Nie. Przeglądanie ofert, kontakt z właścicielami i rezerwacja oglądania są bezpłatne.</p>
<h3>Jak długo trwa weryfikacja ogłoszenia?</h3>
<p>Zwykle do 24 godzin. O decyzji moderatora informujemy w powiadomieniach.</p>
<h3>Czy mogę odwołać oglądanie?</h3>
<p>Tak, w panelu użytkownika w zakładce „Rezerwacje” – do momentu, aż termin się odbędzie.</p>
<h3>Czy umowa wygenerowana w serwisie jest wiążąca?</h3>
<p>Umowa jest wzorem przygotowanym na podstawie danych z ogłoszenia. Przed podpisaniem warto ją przeczytać i w razie potrzeby uzupełnić.</p>
HTML,
            ],
            [
                'title' => 'Regulamin',
                'slug' => 'regulamin',
                'meta_description' => 'Regulamin korzystania z serwisu NajmujMieszkanie.',
                'content' => <<<'HTML'
<h2>§1. Postanowienia ogólne</h2>
<p>Regulamin określa zasady korzystania z serwisu NajmujMieszkanie. Korzystając z serwisu, użytkownik akceptuje jego postanowienia.</p>
<h2>§2. Konta użytkowników</h2>
<p>Użytkownik może założyć konto najemcy lub właściciela. Dane podane przy rejestracji muszą być prawdziwe.</p>
<h2>§3. Ogłoszenia</h2>
<p>Ogłoszenia podlegają moderacji. Administrator może odrzucić ofertę niezgodną z prawem lub wprowadzającą w błąd.</p>
<h2>§4. Odpowiedzialność</h2>
<p>Serwis pośredniczy w kontakcie stron i nie jest stroną umowy najmu.</p>
HTML,
            ],
            [
                'title' => 'Polityka prywatności',
                'slug' => 'polityka-prywatnosci',
                'meta_description' => 'Jak przetwarzamy dane osobowe w serwisie NajmujMieszkanie.',
                'content' => <<<'HTML'
<p>Administratorem danych osobowych jest operator serwisu NajmujMieszkanie. Dane przetwarzamy wyłącznie w celu świadczenia usług serwisu.</p>
<h2>Jakie dane zbieramy</h2>
<p>Imię i nazwisko, adres e-mail, numer telefonu oraz dane podane w ogłoszeniach i wiadomościach.</p>
<h2>Twoje prawa</h2>
<p>Masz prawo dostępu do swoich danych, ich poprawiania i usunięcia. W tym celu skontaktuj się z nami przez formularz kontaktowy.</p>
HTML,
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'meta_title' => $page['title'],
                    'status' => 'published',
                ])
            );
        }
    }
}
