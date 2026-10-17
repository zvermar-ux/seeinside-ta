# Website API (за WordPress плагинот)

Основна адреса: `https://ta.seeinsidesolution.com/api/v1`
Автентикација: заглавие `X-Api-Key: ta_xxxxxxxx_…` (клучот се креира во **Подесувања → Веб-страници (WordPress)**).
Сите текстови доаѓаат на двата јазика: `{"mk": "...", "en": "..."}`.

## Каталог

| Метод | Адреса | Филтри |
|---|---|---|
| GET | `/meta` | агенција, слајдер, дестинации, групи, места на поаѓање, авиокомпании, содржини, енуми, авио widget |
| GET | `/tours` | `q, type, destination, group, featured, flight, last_minute, date_from, date_to, departure_place, price_max, sort=price\|newest, page, per_page` |
| GET | `/tours/{slug}` | програма, датуми, места на поаѓање, доплати, вклучено/невклучено, галерија |
| GET | `/events` | `q, category, city, featured, last_minute, past` |
| GET | `/events/{slug}` | билети, поврзана тура |
| GET | `/accommodations` | `q, type, destination, group, stars, board, amenities=wifi,pool, last_minute, price_max, sort=price\|stars` |
| GET | `/accommodations/{slug}` | соби, цени по периоди, содржини, куќен ред |
| GET | `/accommodations/{slug}/quote?from=YYYY-MM-DD&to=…&adults=2&children=0` | понуди по соба за престојот (само слободните соби, со `available`) |
| GET | `/accommodations/{slug}/availability?from=YYYY-MM-DD&to=…` | слободни единици и цена по соба и ноќ (до 92 дена), за календар на сајтот |
| GET | `/charters` | претстојни чартер места: дестинација, датуми, цена, само превоз, слободни места |
| GET | `/bookings/{reference}?email=` | статус на резервација за клиентот (број + е-пошта) |
| GET | `/visa-applications/{reference}?email=` | статус на барање за виза |
| GET | `/slider` | слајдер на почетна: слика, наднаслов, наслов, поднаслов, копче (текст, линк, нов таб) |
| GET | `/visas`, `/visas/{slug}` | прашалник и документи за формата |
| GET | `/last-minute` | сите last minute понуди (тури, хотели, настани, визи) |

## Синхронизација во реално време

1. **Webhook (push):** при секоја промена системот праќа `POST` на webhook адресата на сајтот:
   ```json
   {"event":"tour.updated","type":"tour","id":5,"slug":"neapol-sorento-amalfi","updated_at":"…"}
   ```
   Заглавие `X-TA-Signature: sha256=<HMAC-SHA256 на телото со webhook тајната>`.
   Настани: `tour|event|accommodation|visa|slider|charter` + `.updated|.deleted` (за `slider` и `charter` плагинот повторно ги повлекува `/slider` и `/charters`). Плагинот го повлекува записот по `slug` и го ажурира.
2. **Резерва (pull):** `GET /changes?since=<server_time од претходниот повик>` враќа `changed[]`, `deleted[]` и ново `server_time`. Плагинот го повикува на секои 15 мин.

## Форми од сајтот

`POST /bookings` (JSON)
```json
{
  "type": "tour", "slug": "neapol-sorento-amalfi",
  "departure_id": 12, "departure_place_id": 3, "extras": [4],
  "adults": 2, "children": 1, "children_ages": [7],
  "customer": {"name": "Ана Анова", "email": "ana@…", "phone": "070…", "city": "Скопје"},
  "passengers": [{"first_name": "Ана", "last_name": "Анова", "birth_date": "1990-01-01", "passport": "A123"}],
  "message": "…", "locale": "mk"
}
```
- настан: `ticket_id`, `quantity`; хотел: `date_from`, `date_to`, `room_id` (опционално); чартер: `"type":"charter"`, `charter_id`, `transport_only` (без `slug`).
- Ако собата е зафатена за избраните ноќи, одговорот е `422` (`room_id`).
- Одговор `201`: `{"reference": "R2610-ABCDE", "status": "inquiry", "total": 1757, "currency": "EUR", "payment": null}`.
- Цената се пресметува на серверот: датум, доплата за место на поаѓање, дополнителни услуги, хотелски ценовник.

`POST /visa-applications` (multipart/form-data)
`visa` (slug), `first_name`, `last_name`, `email`, `phone`, `answers[key]`, `documents[key]` (или `documents[key][]` за повеќе фајлови; pdf/jpg/png/webp/heic, до 10 MB).
Документите се чуваат приватно; персоналот ги отвора од админот.

Грешките за валидација враќаат `422` со `errors`.

## Ограничувања

По веб-страница (API клуч): 1200 барања/минута за каталогот и 120/минута за формите и проверките на статус.
Сите посетители на сајтот доаѓаат од серверот на сајтот, па ограничувањето по посетител го прави WordPress плагинот (10 форми на 10 минути по посетител).
