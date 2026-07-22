# InPost Pay - Wtyczka dla Shopware 6.5

> **Którą wersję zainstalować (dopasuj do swojej wersji Shopware):**
> - Shopware **6.5** → `composer require crehler/inpost-pay:^1.0`
> - Shopware **6.6** → `composer require crehler/inpost-pay:^2.0`
> - Shopware **6.7.0 – 6.7.6** → `composer require crehler/inpost-pay:^3.0`
> - Shopware **6.7.7+** → `composer require crehler/inpost-pay:^4.0`

## Spis treści
- [Wprowadzenie](#wprowadzenie)
- [Co to jest InPost Pay?](#co-to-jest-inpost-pay)
- [Funkcjonalności wtyczki](#funkcjonalności-wtyczki)
- [Wymagania systemowe](#wymagania-systemowe)
- [Instalacja](#instalacja)
- [Konfiguracja](#konfiguracja)
  - [Konfiguracja podstawowa](#konfiguracja-podstawowa)
  - [Konfiguracja metod płatności](#konfiguracja-metod-płatności)
  - [Mapowanie metod dostawy](#mapowanie-metod-dostawy)
  - [Konfiguracja widgetów](#konfiguracja-widgetów)
- [Wymagane ustawienia Shopware](#wymagane-ustawienia-shopware)
- [Jak to działa?](#jak-to-działa)
- [Endpointy API](#endpointy-api)
- [Obsługa Webhooków](#obsługa-webhooków)
- [Rozwiązywanie problemów](#rozwiązywanie-problemów)
- [Wsparcie techniczne](#wsparcie-techniczne)

---

## Wprowadzenie

Wtyczka **InPost Pay** umożliwia integrację platformy e-commerce Shopware 6.5 z systemem płatności InPost Pay. Dzięki tej wtyczce klienci mogą korzystać z różnorodnych metod płatności oferowanych przez InPost Pay, a także z wygodnych widgetów prezentujących informacje o dostępnych opcjach płatności bezpośrednio na stronach produktów, w koszyku oraz podczas procesu finalizacji zamówienia.

---

## Co to jest InPost Pay?

**InPost Pay** to kompleksowa platforma płatnicza oferowana przez firmę InPost, która umożliwia:

- **Płatności online** - obsługę różnych form płatności elektronicznych
- **Odroczone płatności** - możliwość zakupu z odroczonym terminem płatności
- **Raty i limity zakupowe** - elastyczne opcje finansowania zakupów
- **Płatności mobilne** - wsparcie dla Google Pay, Apple Pay, BLIK
- **Bezpieczeństwo transakcji** - zaawansowane mechanizmy weryfikacji i autoryzacji

### Cele biznesowe InPost Pay:

1. **Zwiększenie konwersji** - oferowanie wielu metod płatności zwiększa szanse na finalizację zakupu
2. **Budowanie zaufania** - znana marka InPost zwiększa zaufanie klientów
3. **Elastyczność finansowa** - odroczone płatności i raty umożliwiają zakupy klientom o różnej sytuacji finansowej
4. **Uproszczenie procesu zakupowego** - szybkie płatności mobilne (BLIK, Google Pay, Apple Pay)
5. **Bezpieczeństwo** - nowoczesne mechanizmy bezpieczeństwa chronią sprzedawcę i kupującego

---

## Funkcjonalności wtyczki

### 1. Integracja z systemem płatności InPost Pay
- Dodanie metody płatności InPost Pay w Shopware
- Obsługa procesu płatności z wykorzystaniem API InPost Pay
- Automatyczna synchronizacja statusów płatności

### 2. Widget InPost Pay
Wtyczka wyświetla interaktywne widgety InPost Pay w następujących lokalizacjach:
- **Strona produktu** - informacje o dostępnych metodach płatności dla konkretnego produktu
- **Mini koszyk (offcanvas)** - widget w wysuwanym koszyku
- **Strona podsumowania koszyka** - pełne informacje o metodach płatności
- **Strona checkout** - widget podczas finalizacji zamówienia

Widgety są w pełni konfigurowalne (kolor, rozmiar, styl, marginesy, wyrównanie).

### 3. Synchronizacja koszyka
- Automatyczna synchronizacja zawartości koszyka Shopware z koszykiem InPost Pay
- Aktualizacja koszyka w czasie rzeczywistym przy zmianach (dodanie/usunięcie produktu, zmiana ilości)
- Obsługa potwierdzeń koszyka z poziomu aplikacji InPost Pay

### 4. Tworzenie zamówień
- Automatyczne tworzenie zamówień w Shopware po potwierdzeniu płatności
- Mapowanie danych klienta z InPost Pay do Shopware
- Obsługa danych dostawy i faktury

### 5. Obsługa webhooków
Wtyczka odbiera i przetwarza webhooki od InPost Pay:
- `PAYMENT_AUTHORIZED` - autoryzacja płatności
- `PAYMENT_DECLINED` - odrzucenie płatności
- `REFUND` - potwierdzenie zwrotu środków
- `REFUND_DECLINED` - odrzucenie zwrotu
- `SETTLEMENT` - rozliczenie transakcji

### 6. Mapowanie metod dostawy
Wtyczka umożliwia mapowanie metod dostawy Shopware na typy dostawy InPost Pay:
- **APM (Paczkomat)** - odbiór w paczkomacie InPost
- **Courier** - dostawa kurierska
- **Digital** - produkty cyfrowe

### 7. Wsparcie dla wielu metod płatności
Wtyczka obsługuje następujące metody płatności:
- **BLIK** - szybkie płatności mobilne
- **Karta płatnicza (CARD)** - płatność kartą kredytową/debetową
- **Card Token** - zapisane karty płatnicze
- **Google Pay** - płatności Google
- **Apple Pay** - płatności Apple
- **Pay by Link** - płatność przez link
- **Shopping Limit** - limit zakupowy
- **Deferred Payment** - odroczona płatność
- **Cash on Delivery** - płatność przy odbiorze

---

## Wymagania systemowe

### Wymagania platformy:
- **Shopware**: wersja **6.5.x**
- **PHP**: wersja **8.1**, **8.2** lub **8.3**
- **Composer**: do zarządzania zależnościami

### Wymagania funkcjonalne:
- Aktywne konto InPost Pay (środowisko sandbox lub produkcyjne)
- Certyfikaty API i dane autoryzacyjne (Client ID, Client Secret, Post ID, Merchant Client ID, Merchant Secret)
- Dostęp do konfiguracji Shopware w panelu administracyjnym

### Wymagania Shopware:
- **WAŻNE**: Numer telefonu musi być włączony, wymagany i poprawnie skonfigurowany w formularzach Shopware (rejestracja, checkout)
- Włączone SSL (HTTPS) dla bezpieczeństwa komunikacji
- Dostęp do logów systemowych dla debugowania

---

## Instalacja

### 1. Instalacja przez Composer (zalecane)

```bash
# Przejdź do katalogu głównego Shopware
cd /path/to/shopware

# Zainstaluj wtyczkę
composer require crehler/inpost-pay

# Odśwież wtyczki Shopware
bin/console plugin:refresh

# Zainstaluj wtyczkę
bin/console plugin:install InpostPay --activate

# Wyczyść cache
bin/console cache:clear
```

### 2. Instalacja manualna

```bash
# Skopiuj wtyczkę do katalogu custom/plugins
cp -r InpostPay /path/to/shopware/custom/plugins/

# Odśwież wtyczki
bin/console plugin:refresh

# Zainstaluj wtyczkę
bin/console plugin:install InpostPay --activate

# Wyczyść cache
bin/console cache:clear
```

### 3. Migracja bazy danych

Po instalacji wtyczki zostaną automatycznie uruchomione migracje tworzące wymagane tabele w bazie danych:
- `inpost_basket_session` - tabela przechowująca sesje koszyków InPost Pay

---

## Konfiguracja

### Dostęp do konfiguracji

1. Zaloguj się do panelu administracyjnego Shopware
2. Przejdź do: **Rozszerzenia** → **Moje rozszerzenia** → **Aplikacje**
3. Znajdź wtyczkę **InPost Pay** i kliknij ikonę **ustawień** (⚙️)

---

### Konfiguracja podstawowa

#### 1. InPost Pay Configuration

| Pole | Opis | Wymagane |
|------|------|----------|
| **Enable Sandbox Mode** | Włącza tryb testowy (sandbox). Wyłącz dla produkcji. | Tak |
| **Client ID** | Identyfikator klienta otrzymany od InPost Pay | Tak |
| **Client Secret** | Sekret klienta (nie udostępniaj publicznie) | Tak |
| **Post ID** | Identyfikator punktu sprzedaży | Tak |
| **Merchant Client ID** | Identyfikator merchantowy | Tak |
| **Merchant Secret** | Sekret merchantowy używany do walidacji webhooków | Tak |

**Przykład:**
```
Client ID: b73ca9fc-0123-4567-8912-5ac02c6af6e9
Client Secret: ********************************
Post ID: 12345678
Merchant Client ID: b73ca9fc-0123-4567-8912-5ac02c6af6e9
Merchant Secret: ********************************
```

---

### Konfiguracja metod płatności

#### 2. Payment Methods Configuration

W tej sekcji możesz wybrać, które metody płatności będą dostępne dla klientów w widgecie InPost Pay.

**Dostępne metody:**
- ✅ **BLIK** (domyślnie włączone)
- ✅ **Credit/Debit Card** (domyślnie włączone)
- ⬜ **Card Token**
- ⬜ **Google Pay**
- ⬜ **Apple Pay**
- ✅ **Pay by Link** (domyślnie włączone)
- ⬜ **Shopping Limit**
- ⬜ **Deferred Payment**
- ⬜ **Cash on Delivery**

**Zalecenie:** Włącz metody płatności najpopularniejsze wśród Twoich klientów, ale nie przytłaczaj ich zbyt dużą liczbą opcji.

---

### Mapowanie metod dostawy

#### 3. Delivery Method Mapping

Wtyczka wymaga mapowania metod dostawy Shopware na typy dostawy InPost Pay. Jest to kluczowe dla poprawnego działania integracji.

| Typ dostawy InPost Pay | Opis | Przykładowe metody Shopware |
|------------------------|------|-----------------------------|
| **APM (Paczkomat)** | Odbiór w paczkomacie InPost | "InPost Paczkomat", "Odbiór w paczkomacie" |
| **Courier** | Dostawa kurierska | "DHL", "DPD", "UPS", "InPost Kurier" |
| **Digital** | Produkty cyfrowe | "Pobieranie cyfrowe", "E-book", "Dostawa email" |

**Jak skonfigurować:**
1. Kliknij w pole **APM (Paczkomat) Methods**
2. Wybierz metody dostawy Shopware, które odpowiadają dostawie do paczkomatu
3. Powtórz dla **Courier Delivery Methods** (kurier)
4. Powtórz dla **Digital Delivery Methods** (produkty cyfrowe)

---

### Konfiguracja widgetów

Wtyczka oferuje szczegółową konfigurację wyglądu widgetów dla 4 różnych lokalizacji:

#### 4. Widget Display - Product Card (Strona produktu)

Konfiguracja widgetu wyświetlanego na stronie produktu.

| Pole | Opis | Domyślna wartość |
|------|------|------------------|
| **Display Widget** | Czy wyświetlać widget | ✅ Włączone |
| **Dark Mode** | Ciemny motyw | ⬜ Wyłączone |
| **Variant** | Wariant kolorystyczny | Secondary |
| **Frame Style** | Styl ramki (rounded/round) | - |
| **Size** | Rozmiar (xs/sm/md/lg/xl) | - |
| **Max Width** | Maksymalna szerokość w px (220-1200) | - |
| **Margin Top/Bottom/Left/Right** | Marginesy w px | - |
| **Alignment** | Wyrównanie (Left/Center/Right) | - |

#### 5. Widget Display - Mini Cart (Mini koszyk)

Konfiguracja widgetu w wysuwanym koszyku (offcanvas).

*(Pola identyczne jak w Product Card)*

#### 6. Widget Display - Cart Summary (Podsumowanie koszyka)

Konfiguracja widgetu na stronie koszyka.

*(Pola identyczne jak w Product Card)*

#### 7. Widget Display - Checkout Page (Strona checkout)

Konfiguracja widgetu na stronie finalizacji zamówienia.

*(Pola identyczne jak w Product Card)*

**Zalecenia dotyczące widgetów:**
- Na stronie produktu: rozmiar **sm** lub **md**, wyrównanie **Left**
- W mini koszyku: rozmiar **xs** lub **sm**, wyrównanie **Center**
- Na stronie koszyka: rozmiar **md** lub **lg**, wyrównanie **Center**
- Na checkout: rozmiar **md**, wyrównanie **Center**

---

## Wymagane ustawienia Shopware

### ⚠️ KRYTYCZNE: Konfiguracja numeru telefonu

**Wtyczka InPost Pay wymaga numeru telefonu klienta do poprawnego działania.** Numer telefonu jest niezbędny do:
- Autoryzacji płatności w aplikacji InPost Pay
- Komunikacji z klientem w przypadku problemów z płatnością
- Weryfikacji tożsamości podczas płatności mobilnych (BLIK)

#### Jak włączyć i skonfigurować numer telefonu w Shopware:

1. **Włączenie pola numer telefonu w systemie:**
   - Zaloguj się do panelu administracyjnego Shopware
   - Przejdź do: **Settings** (Ustawienia) → **System** → **Customer groups** (Grupy klientów)
   - Wybierz grupę klientów, którą chcesz edytować (np. "Standard customer group")
   - W sekcji **Registration** (Rejestracja) znajdź opcję **Phone number field**
   - ✅ Zaznacz opcję **Display** (Wyświetl) - aby pole było widoczne
   - ✅ Zaznacz opcję **Required** (Wymagane) - aby pole było obowiązkowe

2. **Alternatywnie - konfiguracja przez Settings → Shop:**
   - Przejdź do: **Settings** → **Shop** → **Customer**
   - W sekcji **Address fields** (Pola adresu) znajdź:
     - **Phone number** → ustaw jako **Display and required** (Wyświetl i wymagane)

3. **Weryfikacja ustawień:**
   - Przejdź na frontend sklepu w trybie incognito
   - Sprawdź formularz rejestracji - pole "Phone number" powinno być widoczne i oznaczone jako wymagane (*)
   - Sprawdź formularz checkout - pole "Phone number" powinno być widoczne w sekcji adresu
   - Spróbuj zarejestrować konto bez numeru telefonu - system powinien wyświetlić błąd walidacji

4. **Weryfikacja w bazie danych:**
   - Sprawdź, czy w tabeli `customer_address` kolumna `phone_number` zawiera dane
   - Możesz to zrobić przez: `SELECT phone_number FROM customer_address LIMIT 10;`

**Format numeru telefonu:**
- Wtyczka obsługuje format międzynarodowy, np. `+48 123 456 789`
- Preferowany jest format z prefiksem kraju (+48 dla Polski)
- System akceptuje również format lokalny, np. `123 456 789`

---

## Jak to działa?

### Przepływ procesu płatności InPost Pay

```
1. Klient dodaje produkty do koszyka
   ↓
2. Widget InPost Pay wyświetla dostępne metody płatności
   ↓
3. Klient klika "Kup teraz z InPost Pay" lub przechodzi do checkout
   ↓
4. Shopware tworzy sesję koszyka i wysyła dane do InPost Pay API
   ↓
5. InPost Pay zwraca ID koszyka i link do aplikacji płatniczej
   ↓
6. Klient zostaje przekierowany do aplikacji InPost Pay
   ↓
7. Klient wybiera metodę płatności i potwierdza zakup
   ↓
8. InPost Pay wysyła webhook z potwierdzeniem płatności
   ↓
9. Shopware aktualizuje status zamówienia
   ↓
10. Klient otrzymuje potwierdzenie zamówienia
```

### Synchronizacja koszyka w czasie rzeczywistym

Wtyczka automatycznie synchronizuje koszyk między Shopware a InPost Pay:

- **Dodanie produktu** → aktualizacja koszyka InPost Pay
- **Usunięcie produktu** → aktualizacja koszyka InPost Pay
- **Zmiana ilości** → aktualizacja koszyka InPost Pay
- **Zmiana metody dostawy** → aktualizacja koszyka InPost Pay

Synchronizacja jest asynchroniczna i nie wpływa na wydajność sklepu.

### Obsługa płatności

#### Statusy płatności (webhooks):

| Webhook InPost Pay | Status transakcji Shopware | Opis |
|-------------------|---------------------------|------|
| `PAYMENT_AUTHORIZED` | `paid` | Płatność została opłacona - zamówienie można realizować |
| `PAYMENT_DECLINED` | `failed` | Płatność została odrzucona - klient musi spróbować ponownie |
| `REFUND` | `refunded` | Zwrot środków został zrealizowany |
| `REFUND_DECLINED` | - | Zwrot odrzucony - wymaga ręcznej interwencji |
| `SETTLEMENT` | - | Transakcja rozliczona (informacja księgowa) |

#### Statusy płatności (order events - `/order/{orderId}/event`):

| PaymentStatus InPost Pay | Status transakcji Shopware | Opis |
|--------------------------|---------------------------|------|
| `AUTHORIZED` | `paid` | Płatność została opłacona |
| `COMPLETED` | `paid` | Płatność zakończona |
| `FAILED` | `failed` | Płatność nieudana |
| `CANCELLED` | `cancelled` | Płatność anulowana |
| `PENDING` | `in_progress` | Płatność w trakcie |
| `REFUNDED` | `refunded` | Zwrot zrealizowany |

**Uwaga:** Status transakcji w Shopware jest aktualizowany automatycznie przez webhooki i eventy. Początkowo każde zamówienie ma status `open` (oczekuje), który jest aktualizowany po otrzymaniu webhooka/eventu od InPost Pay. W odpowiedzi na event zwracamy `order_merchant_status_description` z polskim opisem aktualnego statusu.

---

## Endpointy API

Wtyczka udostępnia następujące endpointy API dla komunikacji z InPost Pay:

### Publiczne API (bez autoryzacji)

#### Koszyk

```
POST   /api/inpost/v1/izi/basket/{basketId}/confirmation
GET    /api/inpost/v1/izi/basket/{basketId}
DELETE /api/inpost/v1/izi/basket/{basketId}/binding
POST   /api/inpost/v1/izi/basket/{basketId}/event
PUT    /api/inpost/v2/izi/basket/{basketId}
```

#### Zamówienie

```
POST   /api/inpost/v1/izi/order
GET    /api/inpost/v1/izi/order/{orderId}
POST   /api/inpost/v1/izi/order/{orderId}/event
```

#### Webhooki

```
POST   /api/inpost/events
```

### Storefront API (wymagana sesja klienta)

```
POST   /checkout/inpost/basket/bind
POST   /checkout/inpost/basket/bindBasket
```

### Zabezpieczenie webhooków

Webhooki od InPost Pay są zabezpieczone podpisem HMAC SHA-256. Wtyczka automatycznie weryfikuje podpis używając `Merchant Secret` z konfiguracji.

---

## Obsługa Webhooków

### Konfiguracja URL webhooka w InPost Pay

Podczas konfiguracji konta InPost Pay podaj następujący URL webhooka:

```
https://twoja-domena.pl/api/inpost/events
```

Zastąp `twoja-domena.pl` rzeczywistą domeną Twojego sklepu.

### Typy webhooków

Wtyczka obsługuje następujące typy webhooków:

#### 1. PAYMENT_AUTHORIZED
Wysyłany, gdy płatność została autoryzowana przez InPost Pay.

**Akcja:** Wtyczka aktualizuje status transakcji na "authorized" (autoryzowana).

**Znaczenie biznesowe:** Płatność została zatwierdzona, ale środki jeszcze nie zostały pobrane. Zamówienie można realizować.

#### 2. PAYMENT_DECLINED
Wysyłany, gdy płatność została odrzucona (np. brak środków, błąd karty).

**Akcja:** Wtyczka aktualizuje status transakcji na "failed" (nieudana).

**Znaczenie biznesowe:** Płatność nie powiodła się. Klient musi spróbować ponownie lub wybrać inną metodę płatności.

#### 3. REFUND
Wysyłany, gdy zwrot środków został pomyślnie przetworzony.

**Akcja:** Wtyczka aktualizuje status transakcji na "refunded" (zwrócona) i tworzy notatkę o zwrocie.

**Znaczenie biznesowe:** Środki zostały zwrócone klientowi. Proces zwrotu zakończony.

#### 4. REFUND_DECLINED
Wysyłany, gdy próba zwrotu została odrzucona.

**Akcja:** Wtyczka loguje błąd i tworzy notatkę o nieudanym zwrocie.

**Znaczenie biznesowe:** Zwrot nie powiódł się - wymaga ręcznej interwencji.

#### 5. SETTLEMENT
Wysyłany, gdy transakcja została rozliczona (środki przekazane do sprzedawcy).

**Akcja:** Wtyczka loguje informację o rozliczeniu dla celów księgowych.

**Znaczenie biznesowe:** Transakcja została ostatecznie rozliczona - środki trafiły na konto sprzedawcy.

### Debugowanie webhooków

Webhooki są logowane w logach Shopware. Aby włączyć szczegółowe logowanie:

1. Przejdź do pliku `.env` w katalogu głównym Shopware
2. Ustaw poziom logowania na `debug`:
   ```
   APP_ENV=dev
   APP_DEBUG=1
   ```
3. Logi znajdziesz w: `var/log/dev.log`

**Przykładowy log webhooka:**
```
[2025-12-01 10:30:45] app.INFO: Sprawdzamy webhooka:
[2025-12-01 10:30:45] app.INFO: Webhook event processed successfully {"event_type":"PAYMENT_AUTHORIZED","order_id":"abc123"}
```

**Testowanie webhooków:**
Możesz ręcznie przetestować endpoint webhooków używając narzędzi jak curl lub Postman:

```bash
curl -X POST https://twoja-domena.pl/api/inpost/events \
  -H "Content-Type: application/json" \
  -H "X-API-Version: 1.0" \
  -H "X-Signature: test-signature" \
  -d '{
    "eventType": "PAYMENT_AUTHORIZED",
    "eventData": {
      "orderReference": "test-order-123",
      "status": "AUTHORIZED"
    }
  }'
```

**Uwaga:** W środowisku produkcyjnym webhook wymaga poprawnego podpisu HMAC.

---

## Rozwiązywanie problemów

### Problem: Widget InPost Pay nie wyświetla się na stronie

**Rozwiązanie:**
1. Sprawdź, czy wtyczka jest aktywna: `bin/console plugin:list | grep InpostPay`
2. Sprawdź konfigurację widgetów - upewnij się, że "Display Widget" jest włączone
3. Wyczyść cache: `bin/console cache:clear`
4. Sprawdź logi przeglądarki (F12 → Console) w poszukiwaniu błędów JavaScript

### Problem: Błąd "Basket session not found"

**Rozwiązanie:**
1. Sprawdź, czy sesja koszyka została utworzona
2. Sprawdź, czy nie upłynął timeout sesji
3. Sprawdź logi: `var/log/dev.log`

### Problem: Płatność nie jest aktualizowana po potwierdzeniu

**Rozwiązanie:**
1. Sprawdź, czy URL webhooka jest poprawnie skonfigurowany w InPost Pay
2. Sprawdź, czy Merchant Secret jest poprawny
3. Sprawdź logi webhooków: `var/log/dev.log | grep webhook`
4. Sprawdź, czy serwer jest dostępny publicznie (nie localhost)

### Problem: Błąd "Invalid webhook signature"

**Rozwiązanie:**
1. Sprawdź, czy Merchant Secret w konfiguracji jest poprawny
2. Upewnij się, że używasz tej samej wartości co w panelu InPost Pay
3. Sprawdź, czy webhook zawiera wymagane nagłówki: `X-API-Version`, `X-Signature`

### Problem: Brak numeru telefonu w zamówieniu

**Rozwiązanie:**
1. Sprawdź konfigurację pól klienta w Shopware (patrz: [Wymagane ustawienia Shopware](#wymagane-ustawienia-shopware))
2. Upewnij się, że numer telefonu jest oznaczony jako wymagany
3. Przetestuj formularz rejestracji i checkout

### Problem: Błąd "Client credentials are invalid"

**Rozwiązanie:**
1. Sprawdź, czy Client ID i Client Secret są poprawne
2. Upewnij się, że używasz właściwego środowiska (sandbox vs. production)
3. Sprawdź, czy certyfikaty nie wygasły
4. Skontaktuj się z działem technicznym InPost Pay

### Problem: Webhook nie aktualizuje statusu zamówienia

**Rozwiązanie:**
1. Sprawdź logi webhooka: `tail -f var/log/dev.log | grep -i inpost`
2. Upewnij się, że webhook zawiera poprawny typ zdarzenia: `PAYMENT_AUTHORIZED`, `PAYMENT_DECLINED`, `REFUND`, `REFUND_DECLINED` lub `SETTLEMENT`
3. Sprawdź, czy `orderReference` w webhooku odpowiada istniejącemu zamówieniu w Shopware
4. Zweryfikuj, czy webhooki są faktycznie wysyłane z InPost Pay - sprawdź w panelu InPost Pay historię webhooków
5. Przetestuj endpoint ręcznie za pomocą curl (patrz sekcja "Testowanie webhooków")

---

## Wsparcie techniczne

### Wsparcie wtyczki
- **Email:** support@crehler.com
- **Strona WWW:** http://crehler.com
- **GitHub:** [Zgłoś problem](https://github.com/crehler/inpost-pay/issues) (jeśli dostępne)

### Wsparcie InPost Pay
- **Strona WWW:** https://www.inpost.pl
- **Dokumentacja API:** https://developer.inpost.pl
- **Pomoc techniczna:** Skontaktuj się przez panel InPost Pay

### Przydatne komendy Shopware

```bash
# Odśwież listę wtyczek
bin/console plugin:refresh

# Aktywuj wtyczkę
bin/console plugin:install InpostPay --activate

# Dezaktywuj wtyczkę
bin/console plugin:deactivate InpostPay

# Odinstaluj wtyczkę
bin/console plugin:uninstall InpostPay

# Wyczyść cache
bin/console cache:clear

# Uruchom migracje
bin/console database:migrate --all InpostPay

# Wyświetl logi
tail -f var/log/dev.log
```

---

## Licencja

Wtyczka jest udostępniana na licencji MIT.

## Autor

**Crehler Sp. z o. o.**
- Email: support@crehler.com
- WWW: http://crehler.com

---

**Wersja dokumentacji:** 1.0.0
**Data:** 2025-12-01
**Wersja wtyczki:** 1.0.0

## 📅 Kalendarium
Utworzono: 14.11.2025
Ostatnia modyfikacja: 07.01.2026

