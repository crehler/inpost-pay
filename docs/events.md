# Eventy rozszerzeń InPost Pay

Publiczne extension pointy (Symfony Events, namespace `Crehler\InpostPay\Application\Event`),
przez które inne wtyczki mogą modyfikować dane wymieniane z InPost — bez nadpisywania
serwisów wtyczki. Wszystkie klasy rozszerzają `Symfony\Contracts\EventDispatcher\Event`
(można użyć `stopPropagation()`).

## Dane wychodzące (sklep → InPost)

| Event | Kiedy | Mutowalne |
|---|---|---|
| `BasketPayloadBuiltEvent` | po zbudowaniu payloadu koszyka, przed wysyłką/zwrotem — obejmuje odpowiedź na confirmation, GET basket, odpowiedź na basket event i PUT update koszyka | `payload` (array) przez `setPayload()` |
| `OrderPayloadBuiltEvent` | po zbudowaniu payloadu zamówienia (odpowiedzi createOrder i getOrder) | `payload` przez `setPayload()` |
| `OrderUpdatePayloadBuiltEvent` | przed wypchnięciem aktualizacji statusu zamówienia (`POST /order/{id}/event`) | `payload` przez `setPayload()` |

## Dane przychodzące (InPost → sklep)

| Event | Kiedy | Mutowalne |
|---|---|---|
| `InpostAddressMappedEvent` | po zmapowaniu adresu InPost (client_address / delivery_address) na pola adresu Shopware, przed zapisem i przed deduplikacją adresów klienta | `addressData` (street, zipcode, city, countryId, firstName, lastName, phoneNumber, additionalAddressLine1) przez `setAddressData()` |
| `OrderDataPreparedEvent` | po pełnym przygotowaniu danych zamówienia (nadpisane adresy, dane faktury), tuż przed `order.repository->create()` | `orderData` przez `setOrderData()` |
| `BasketEventReceivedEvent` | po odebraniu basket eventu od InPost (zmiana ilości / kod promocyjny / related product), przed zastosowaniem na koszyku | koszyk przez referencję (`getCart()`) |

Istniejący wcześniej `OrderCartPreparedEvent` (koszyk załadowany, przed rekalkulacją
i konwersją na zamówienie) pozostaje bez zmian.

## Przykład: rozdzielenie numeru domu (SUEZ-1097)

InPost przysyła `street`/`building`/`flat` osobno, a wtyczka skleja je w jedno pole
`street` ("Kwiatowa 12/3"). Listener może to rozdzielić:

```php
use Crehler\InpostPay\Application\Event\InpostAddressMappedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SplitBuildingNumberSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [InpostAddressMappedEvent::class => 'onAddressMapped'];
    }

    public function onAddressMapped(InpostAddressMappedEvent $event): void
    {
        $details = $event->getInpostAddress()->details;
        if ($details === null || !$details->street || !$details->building) {
            return;
        }

        $data = $event->getAddressData();
        $data['street'] = $details->street;
        $data['additionalAddressLine1'] = trim($details->building . ($details->flat ? '/' . $details->flat : ''));
        $event->setAddressData($data);
    }
}
```

Zmapowane dane służą też do deduplikacji adresów klienta, więc modyfikacja
w listenerze nie powoduje zakładania duplikatów przy kolejnych zamówieniach.
