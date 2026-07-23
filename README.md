# tbbapp-backend

Tekirdağ Büyükşehir Belediyesi harita uygulamasının "middleman API"si — `koordinatlar` / `ilceler` / `harita_filtre` tablolarını, frontend'deki harita modülünün (`src/components/map/`, bkz. `MAP.md`) beklediği JSON şekline 1:1 taşır. Kontrat `src/components/map/types.ts` dosyasındaki tiplerle senkron tutulur; bu dosyadaki her response şekli o dosyadaki karşılık gelen tipe atıfla belirtilmiştir.

## Kurulum

```bash
docker-compose up -d          # Postgres'i ayağa kaldır (5433 portu)
cp .env.example .env          # DATABASE_URL / PORT / NODE_ENV / ADMIN_API_KEY
npm install
npx prisma migrate deploy     # şemayı uygula
node prisma/seed.js           # ilçe/kategori/yer örnek verisini yükle
npm run dev                   # http://localhost:3000 (dosya değişiminde otomatik reload)
```

`.env`'deki `ADMIN_API_KEY`'i rastgele bir değerle değiştir — admin endpoint'lerini bu korur.

`npm start` reload'sız prod modunda çalıştırır.

## Genel davranış

- Tüm response'lar JSON.
- Hatalar `{ "error": "..." }` şeklinde döner; body doğrulama hataları `400`, bulunamayan kayıtlar `404`, beklenmeyen sunucu hataları `500` (production'da mesaj `"Sunucu hatası"`ya sabitlenir, stack trace sızdırılmaz).
- Bilinmeyen route'lar `404 { "error": "Bulunamadı" }` döner.
- `POST /feedback` ve `POST /new-place-requests` IP başına 15 dakikada 20 istekle sınırlıdır (spam/abuse koruması); limit aşılırsa `429` döner.
- `GET`/`PATCH` uçları (admin) hariç tüm endpoint'ler herkese açık — mobil app hiçbir kimlik doğrulaması olmadan tüketiyor.
- **Admin endpoint'leri** (`GET /feedback`, `GET /new-place-requests`, `PATCH /new-place-requests/:id`) `x-admin-key` header'ında `ADMIN_API_KEY` ile eşleşen bir değer ister; eksik/yanlışsa `401 { "error": "Yetkisiz" }` döner. Kullanıcı/rol sistemi yok, tek paylaşılan bir sır — ihtiyaç büyürse gerçek bir auth'a geçilebilir.

## Endpoint'ler

### `GET /districts`

İlçe listesini döner. → `MapDistrict[]`

```json
[{ "id": 1, "name": "Süleymanpaşa" }]
```

### `GET /categories`

Harita filtre kategorilerini `sira`'ya göre sıralı döner. → `MapCategory[]`

> `sira` alanı JSON'a gömülmez — sıralama dizinin kendi sırasıyla taşınır (bkz. `types.ts` yorumu).

```json
[{ "id": 1, "title": "Belediye" }]
```

### `GET /places`

Yerleri döner. → `MapPlace[]`

| Query param | Tip | Açıklama |
|---|---|---|
| `districtId` | pozitif tamsayı, opsiyonel | Belirli bir ilçeye göre filtreler |
| `categoryId` | pozitif tamsayı, opsiyonel | Belirli bir kategoriye göre filtreler |

Geçersiz bir `districtId`/`categoryId` (sayı olmayan, negatif, sıfır) `400` döner.

```json
[
  {
    "id": 1,
    "districtId": 1,
    "categoryId": 1,
    "name": "Tekirdağ Büyükşehir Belediyesi",
    "latitude": 40.9778,
    "longitude": 27.5147,
    "description": "Büyükşehir belediyesinin ana hizmet binası..."
  }
]
```

`description` yoksa `null` döner (frontend placeholder metne düşer). Nesne bilerek düz (flat) — `district`/`category` nested olarak gömülmez; frontend zaten `districts`/`categories` listelerini ayrıca tutuyor ve id ile eşleştiriyor.

### `GET /places/:id`

Tek bir yerin detayını döner. → `MapPlace`

- Geçersiz `id` (sayı değil, ≤0) → `400`
- Bulunamayan `id` → `404`

### `POST /feedback`

Yeni şikayet/talep kaydı oluşturur (`sikayet_talepler`).

| Alan | Tip | Zorunlu | Açıklama |
|---|---|---|---|
| `kind` | `"complaint" \| "request"` | evet | DB'de enum (`FeedbackKind`) olarak zorunlu kılınır — `MapFeedbackKind` |
| `description` | string (trim edilir, boş olamaz) | evet | |
| `placeId` | pozitif tamsayı | hayır | Var olan bir `Place`'e referans |
| `latitude` / `longitude` | sonlu sayı | hayır | Konum bağlamı yoksa `null` gönderilir |

Şikayetin/talebin `place` ya da `coordinates` bağlamı olabilir ya da hiçbiri (drawer'daki genel Şikayet/Talep butonları) — frontend'in `MapFeedbackSubmission` (nested `place`/`coordinates`) objesini bu düz body'ye çeviren entegrasyon kodu (`onSubmitFeedback` seam) frontend tarafında yazılır.

Başarılı istek `201` ile oluşturulan kaydı döner. Geçersiz `kind` → `400 { "error": "kind şunlardan biri olmalıdır: complaint, request" }`.

### `GET /feedback` — admin

`x-admin-key` gerektirir. Şikayet/talepleri en yeniden eskiye listeler.

| Query param | Tip | Açıklama |
|---|---|---|
| `kind` | `"complaint" \| "request"`, opsiyonel | Türe göre filtreler |

### `POST /new-place-requests`

Yeni yer önerisi oluşturur (`yeni_yer_talepleri`), `status: "pending"` ile başlar. → body şekli `MapNewPlaceSubmission` ile birebir aynı.

| Alan | Tip | Zorunlu |
|---|---|---|
| `name` | string (trim edilir, boş olamaz) | evet |
| `description` | string (trim edilir, boş olamaz) | evet |
| `latitude` / `longitude` | sonlu sayı | evet |
| `categoryId` | pozitif tamsayı | hayır (`null` olabilir) |

Başarılı istek `201` ile oluşturulan kaydı döner.

### `GET /new-place-requests` — admin

`x-admin-key` gerektirir. Yeni yer önerilerini en yeniden eskiye listeler.

| Query param | Tip | Açıklama |
|---|---|---|
| `status` | `"pending" \| "approved" \| "rejected"`, opsiyonel | Duruma göre filtreler |

### `PATCH /new-place-requests/:id` — admin

`x-admin-key` gerektirir. Bir öneriyi onaylar veya reddeder. Sadece `pending` durumundaki öneriler işlenebilir — zaten karara bağlanmış birine tekrar istek atılırsa `409` döner.

| Alan | Tip | Zorunlu | Açıklama |
|---|---|---|---|
| `status` | `"approved" \| "rejected"` | evet | |
| `districtId` | pozitif tamsayı | `status: "approved"` iken evet | Öneri formu ilçe toplamaz; admin onaylarken seçer |
| `categoryId` | pozitif tamsayı | hayır | Önerideki `categoryId` boşsa onay için zorunlu, doluysa override eder |

- `status: "rejected"` → sadece kaydın `status`'unu günceller, `200` ile güncel öneriyi döner.
- `status: "approved"` → `districtId`/`categoryId` doğrulanır (var olmalı), ardından tek bir transaction içinde öneri verilerinden gerçek bir `Place` (`koordinatlar`) satırı oluşturulur ve öneri `approved` olarak işaretlenir. `200` ile `{ "submission": {...}, "place": {...} }` döner.
- Geçersiz `districtId`/`categoryId` (yok/silinmiş) → `400`.

## Veritabanı şeması

Bkz. [prisma/schema.prisma](prisma/schema.prisma). Türkçe tablo/kolon adları (`ilceler`, `koordinatlar`, `enlem`, `boylam` vb.) `@map`/`@@map` ile İngilizce Prisma model adlarına eşlenir — kod tarafı İngilizce, DB tarafı Türkçe kalır.

## Henüz yok (bilinen kapsam dışı)

- Otomatik test yok.
- Gerçek kullanıcı/rol sistemi yok — admin tarafı tek paylaşılan bir API key ile korunuyor.
- Rate limiter bellek içi (in-memory); birden fazla instance ile yatay ölçeklendirilirse paylaşımlı bir store (ör. Redis) gerekir.
- `/` sağlık kontrolü DB bağlantısını test etmiyor — DB down olsa bile `200 ok` döner.
