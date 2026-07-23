# tbbapp-backend

Tekirdağ Büyükşehir Belediyesi harita uygulamasının "middleman API"si — `koordinatlar` / `ilceler` / `harita_filtre` tablolarını, frontend'deki harita modülünün (`src/components/map/`, bkz. `MAP.md`) beklediği JSON şekline 1:1 taşır. Kontrat `src/components/map/types.ts` dosyasındaki tiplerle senkron tutulur; bu dosyadaki her response şekli o dosyadaki karşılık gelen tipe atıfla belirtilmiştir.

## Kurulum

```bash
docker-compose up -d          # Postgres'i ayağa kaldır (5433 portu)
cp .env.example .env          # DATABASE_URL / PORT / NODE_ENV / ADMIN_API_KEY / JWT_SECRET
npm install
npx prisma migrate deploy     # şemayı uygula
node prisma/seed.js           # ilçe/kategori/yer örnek verisini yükle
npm run dev                   # http://localhost:3000 (dosya değişiminde otomatik reload)
```

`.env`'deki `ADMIN_API_KEY`'i ve `JWT_SECRET`'ı rastgele değerlerle değiştir — sırasıyla admin endpoint'lerini ve kullanıcı oturumlarını (JWT imzası) bunlar korur.

`npm start` reload'sız prod modunda çalıştırır.

## Testler

```bash
npm test
```

`tests/` altındaki supertest tabanlı testler `docker-compose up -d` ile ayağa kalkmış **gerçek** dev veritabanına karşı çalışır (mock yok) — çalıştırmadan önce DB'nin ayakta ve migration'ların uygulanmış olduğundan emin ol. Her test dosyası oluşturduğu kayıtları kendi `afterAll`'ında siler, seed verisine dokunmaz.

## Genel davranış

- Tüm response'lar JSON.
- Hatalar `{ "error": "..." }` şeklinde döner; body doğrulama hataları `400`, bulunamayan kayıtlar `404`, beklenmeyen sunucu hataları `500` (production'da mesaj `"Sunucu hatası"`ya sabitlenir, stack trace sızdırılmaz).
- Bilinmeyen route'lar `404 { "error": "Bulunamadı" }` döner.
- `POST /feedback` ve `POST /new-place-requests` IP başına 15 dakikada 20 istekle sınırlıdır (spam/abuse koruması); limit aşılırsa `429` döner.
- `GET /districts`, `GET /categories`, `GET /places`, `GET /places/:id` ve `POST /auth/*` herkese açık, kimlik doğrulama gerektirmez.
- **Vatandaş oturumu:** `POST /feedback` ve `POST /new-place-requests` giriş yapmış bir kullanıcı gerektirir — `Authorization: Bearer <token>` header'ı ister (bkz. aşağıdaki `/auth` bölümü); eksik/geçersizse `401` döner. Kaydı oluşturan kullanıcı token'dan çözülür, body'de ayrıca isim/kimlik bilgisi gönderilmez.
- **Admin endpoint'leri** (`GET /feedback`, `GET /new-place-requests`, `PATCH /new-place-requests/:id`) `x-admin-key` header'ında `ADMIN_API_KEY` ile eşleşen bir değer ister; eksik/yanlışsa `401 { "error": "Yetkisiz" }` döner. Bu, vatandaş oturumundan (JWT) ayrı bir katman — admin tarafında hâlâ kullanıcı/rol sistemi yok, tek paylaşılan bir sır.

## Endpoint'ler

### `POST /auth/register`

Vatandaş kaydı oluşturur, giriş için kullanılacak JWT'yi döner.

| Alan | Tip | Zorunlu |
|---|---|---|
| `firstName` / `lastName` | string (trim, ≤100 karakter) | evet |
| `email` | geçerli email, ≤254 karakter | evet |
| `password` | string, ≥8 karakter | evet |

`201` ile `{ "token": "...", "user": { "id", "firstName", "lastName", "email" } }` döner (şifre hiçbir zaman response'a dahil edilmez). Email zaten kayıtlıysa `409`.

### `POST /auth/login`

| Alan | Tip | Zorunlu |
|---|---|---|
| `email` | string | evet |
| `password` | string | evet |

`200` ile `{ "token": "...", "user": {...} }` döner. Yanlış email/şifre → `401` (hangisinin yanlış olduğu ayırt edilmez; kullanıcı bulunamasa bile şifre karşılaştırması sahte bir hash'e karşı çalıştırılır — email enumeration'ı zorlaştırmak için).

Token, sonraki isteklerde `Authorization: Bearer <token>` header'ı olarak gönderilir; 30 gün geçerli.

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

`Authorization: Bearer <token>` gerektirir. Giriş yapmış kullanıcı adına yeni şikayet/talep kaydı oluşturur (`sikayet_talepler`) — kullanıcı token'dan çözülür, body'de isim gönderilmez.

| Alan | Tip | Zorunlu | Açıklama |
|---|---|---|---|
| `kind` | `"complaint" \| "request"` | evet | DB'de enum (`FeedbackKind`) olarak zorunlu kılınır — `MapFeedbackKind` |
| `description` | string (trim edilir, boş olamaz, ≤2000 karakter) | evet | |
| `placeId` | pozitif tamsayı | hayır | Var olan bir `Place`'e referans |
| `latitude` / `longitude` | sonlu sayı | hayır | Konum bağlamı yoksa `null` gönderilir |

Şikayetin/talebin `place` ya da `coordinates` bağlamı olabilir ya da hiçbiri (drawer'daki genel Şikayet/Talep butonları) — frontend'in `MapFeedbackSubmission` (nested `place`/`coordinates`) objesini bu düz body'ye çeviren entegrasyon kodu (`onSubmitFeedback` seam) frontend tarafında yazılır.

Başarılı istek `201` ile oluşturulan kaydı döner. Token yoksa/geçersizse `401`. Geçersiz `kind` → `400 { "error": "kind şunlardan biri olmalıdır: complaint, request" }`.

### `GET /feedback` — admin

`x-admin-key` gerektirir. Şikayet/talepleri en yeniden eskiye, gönderen kullanıcının `firstName`/`lastName`/`email` bilgisiyle birlikte listeler.

| Query param | Tip | Açıklama |
|---|---|---|
| `kind` | `"complaint" \| "request"`, opsiyonel | Türe göre filtreler |

### `POST /new-place-requests`

`Authorization: Bearer <token>` gerektirir. Giriş yapmış kullanıcı adına yeni yer önerisi oluşturur (`yeni_yer_talepleri`), `status: "pending"` ile başlar. → body şekli `MapNewPlaceSubmission` ile birebir aynı.

| Alan | Tip | Zorunlu |
|---|---|---|
| `name` | string (trim edilir, boş olamaz, ≤200 karakter) | evet |
| `description` | string (trim edilir, boş olamaz, ≤2000 karakter) | evet |
| `latitude` / `longitude` | sonlu sayı | evet |
| `categoryId` | pozitif tamsayı | hayır (`null` olabilir) |

Başarılı istek `201` ile oluşturulan kaydı döner. Token yoksa/geçersizse `401`.

### `GET /new-place-requests` — admin

`x-admin-key` gerektirir. Yeni yer önerilerini en yeniden eskiye, gönderen kullanıcının `firstName`/`lastName`/`email` bilgisiyle birlikte listeler.

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

Bkz. [prisma/schema.prisma](prisma/schema.prisma). Türkçe tablo/kolon adları (`ilceler`, `koordinatlar`, `enlem`, `boylam` vb.) `@map`/`@@map` ile İngilizce Prisma model adlarına eşlenir — kod tarafı İngilizce, DB tarafı Türkçe kalır. `sikayet_talepler.kind`, `yeni_yer_talepleri.status`/`createdAt` alanlarında admin listeleme sorguları için index var.

## Denetim notları

- **Onay endpoint'i (`PATCH /new-place-requests/:id`) race-condition'a karşı sağlamlaştırıldı** — status geçişi artık `WHERE status:'pending'` şartlı atomik bir `updateMany` ile yapılıyor; eşzamanlı iki onay isteğinde sadece biri başarılı olur, diğeri `409` alır (bkz. `tests/newPlaceRequests.test.js`, "eşzamanlı iki onay isteği" testi). Eskiden check-then-act deseniydi ve teorik olarak aynı öneriden iki `Place` doğurabilirdi.
- **Admin key karşılaştırması sabit zamanlı** ([lib/adminAuth.js](lib/adminAuth.js)) — `crypto.timingSafeEqual` ile, timing attack'e karşı.
- **`description`/`name` alanlarına üst uzunluk sınırı** (description 2000, name 200 karakter) — junk/abuse verisine karşı.
- **`.gitattributes`/`.editorconfig`** eklendi — satır sonu (CRLF/LF) tutarsızlığını ve `git add` sırasındaki uyarıları kapatır.
- **`docker-compose.yml`**'e Postgres için `healthcheck` eklendi.
- **`GET /` sağlık kontrolü artık gerçekten DB'ye ping atıyor** (`SELECT 1`) — eskiden DB down olsa bile `200 ok` dönerdi, artık `503` dönüyor.
- **Vatandaş kullanıcı sistemi eklendi** (`User` modeli, `POST /auth/register`/`POST /auth/login`, JWT tabanlı `requireAuth`) — `POST /feedback` ve `POST /new-place-requests` artık giriş yapmayı zorunlu kılıyor, kaydı oluşturan kullanıcı token'dan otomatik bağlanıyor; admin artık kimin şikayet/öneri gönderdiğini görebiliyor.

## Yük altında dayanıklılık

Harita her açıldığında `districts`/`categories`/`places` okunuyor — çok sayıda kullanıcı aynı anda haritayı açtığında bu üç uç en çok vurulan noktalar. Buna karşı:

- **In-memory TTL cache** ([lib/cache.js](lib/cache.js), [lib/caches.js](lib/caches.js)) — `districts`/`categories` 10 dk, `places` 1 dk cache'lenir; `places` filtreleri (`?districtId=`/`?categoryId=`) DB'ye gitmeden bellekteki listeden filtrelenir. Bir öneri onaylanıp yeni `Place` oluştuğunda cache anında invalidate edilir ([routes/newPlaceRequests.js](routes/newPlaceRequests.js)).
- **Postgres bağlantı havuzu sınırlı ve hataya dayanıklı** ([lib/prisma.js](lib/prisma.js)) — `max: 20` bağlantı, `onPoolError`/`onConnectionError` dinleyicileri. Bu dinleyiciler olmadan, havuzdaki bir bağlantı hata verdiğinde (ör. DB tarafından zaman aşımıyla kapatılan idle bağlantı) Node bunu unhandled event olarak fırlatıp **tüm process'i çökertir** — node-postgres'in bilinen bir tuzağı, yoğun trafikte gerçek bir çökme sebebi.
- **`compression`** — yanıtlar gzip'lenir, yüksek eşzamanlılıkta bant genişliği/gecikmeyi azaltır.
- **`uncaughtException`/`unhandledRejection` güvenlik ağı** ([index.js](index.js)) — request-response döngüsü dışında oluşan beklenmedik bir hata process'i bozuk durumda bırakmak yerine loglayıp temiz kapatır (gerçek dayanıklılık için bunu bir restart policy'yle — docker/pm2 — çalıştırmak gerekir).

### Load test

```bash
npm run loadtest
```

`scripts/loadtest.js`, [autocannon](https://github.com/mcollina/autocannon) ile `districts`/`categories`/`places` uçlarına varsayılan olarak **300 eşzamanlı bağlantı × 15 saniye** yük bindirir (`LOAD_TEST_CONNECTIONS`/`LOAD_TEST_DURATION` env değişkenleriyle ayarlanabilir) ve hata/timeout sayısını raporlar. Son ölçüm: 4 senaryoda toplam ~650k istek, **0 hata**, p99 gecikme ~41-54ms.

## Henüz yok (bilinen kapsam dışı)

- Admin tarafında gerçek kullanıcı/rol sistemi yok — tek paylaşılan bir API key ile korunuyor (vatandaş tarafında artık gerçek hesaplar var, bkz. yukarısı).
- Şifre sıfırlama / email doğrulama akışı yok — kayıt anında hesap doğrudan aktif olur.
- Access/refresh token ayrımı yok — tek, 30 gün geçerli bir JWT var; token çalınırsa süresi dolana kadar geçerli kalır (revoke mekanizması yok).
- Cache ve rate limiter bellek içi (in-memory); birden fazla instance ile yatay ölçeklendirilirse paylaşımlı bir store (ör. Redis) gerekir.
