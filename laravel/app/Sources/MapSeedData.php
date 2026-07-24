<?php

namespace App\Sources;

/**
 * The canonical mock dataset (ratified decision: seeders ARE the mock system).
 * One dataset, two consumers:
 *
 *   - MockLocationSource serves these rows directly (MAP_SOURCE=mock — the
 *     API works with no database at all);
 *   - MapSeeder writes them into PostgreSQL (MAP_SOURCE=database).
 *
 * Rows are RAW source rows — status and province_id included. No filtering
 * happens here; the application layer (MapBootstrapService) owns the serving
 * rules, whatever the source.
 */
class MapSeedData
{
    /**
     * Freshness marker for the mock source. Static data → a constant → the
     * mock ETag never rotates. Bump by one whenever this dataset is edited.
     * (v2: districts + descriptions added, 2026-07-24.)
     */
    public const VERSION = 2;

    /**
     * @return list<array{id: int, title: string, status: string}>
     */
    public static function districts(): array
    {
        return [
            ['id' => 1, 'title' => 'Süleymanpaşa', 'status' => 'active'],
            ['id' => 2, 'title' => 'Çorlu', 'status' => 'active'],
            ['id' => 3, 'title' => 'Çerkezköy', 'status' => 'active'],
            ['id' => 4, 'title' => 'Şarköy', 'status' => 'active'],
            ['id' => 5, 'title' => 'Malkara', 'status' => 'active'],
            ['id' => 6, 'title' => 'Marmaraereğlisi', 'status' => 'active'],
            ['id' => 7, 'title' => 'Kapaklı', 'status' => 'active'],
            ['id' => 8, 'title' => 'Hayrabolu', 'status' => 'active'],
            ['id' => 9, 'title' => 'Muratlı', 'status' => 'active'],
            ['id' => 10, 'title' => 'Saray', 'status' => 'active'],
            ['id' => 11, 'title' => 'Ergene', 'status' => 'active'],
        ];
    }

    /**
     * @return list<array{id: int, title: string, status: string}>
     */
    public static function categories(): array
    {
        return [
            ['id' => 1, 'title' => 'Belediye', 'status' => 'active'],
            ['id' => 2, 'title' => 'Sahil', 'status' => 'active'],
            ['id' => 3, 'title' => 'Kültür', 'status' => 'active'],
            ['id' => 4, 'title' => 'Alışveriş', 'status' => 'active'],
            ['id' => 5, 'title' => 'Spor', 'status' => 'active'],
            ['id' => 6, 'title' => 'Toplum', 'status' => 'active'],
            ['id' => 7, 'title' => 'Kent Lokantası', 'status' => 'active'],
            ['id' => 8, 'title' => 'Otopark', 'status' => 'active'],
            ['id' => 9, 'title' => 'Park', 'status' => 'active'],
            ['id' => 10, 'title' => 'Ulaşım', 'status' => 'active'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function places(): array
    {
        return [
            ['id' => 1, 'title' => 'Tekirdağ Büyükşehir Belediyesi', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9778, 'long' => 27.5147, 'status' => 'active', 'category_id' => 1, 'description' => 'Büyükşehir belediyesinin ana hizmet binası; nikah, ruhsat ve genel başvuru işlemleri burada yürütülür.'],
            ['id' => 2, 'title' => 'Süleymanpaşa Sahil', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.974, 'long' => 27.516, 'status' => 'active', 'category_id' => 2, 'description' => 'Kent merkezinin yürüyüş ve dinlenme alanı; sahil bandı boyunca kafeler ve oturma alanları bulunur.'],
            ['id' => 3, 'title' => 'Rakoczi Müzesi', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9731, 'long' => 27.5112, 'status' => 'active', 'category_id' => 3, 'description' => 'Macar prensi II. Ferenc Rakoczi’nin sürgün yıllarını geçirdiği ev; Türk-Macar dostluğunun simgesi.'],
            ['id' => 4, 'title' => 'Tekira AVM', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.982, 'long' => 27.513, 'status' => 'active', 'category_id' => 4, 'description' => 'Şehrin en büyük alışveriş merkezi; mağazalar, sinema ve yemek katı.'],
            ['id' => 5, 'title' => 'Namık Kemal Stadyumu', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.985, 'long' => 27.505, 'status' => 'active', 'category_id' => 5, 'description' => 'Tekirdağspor’un iç saha maçlarını oynadığı şehir stadyumu.'],
            ['id' => 6, 'title' => '100. Yıl Gençlik Merkezi', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9805, 'long' => 27.519, 'status' => 'active', 'category_id' => 6, 'description' => 'Gençlere yönelik kurslar, atölyeler ve etkinliklerin düzenlendiği merkez.'],
            ['id' => 7, 'title' => 'Kent Lokantası Süleymanpaşa', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9762, 'long' => 27.5089, 'status' => 'active', 'category_id' => 7, 'description' => 'Belediyenin uygun fiyatlı yemek hizmeti sunduğu kent lokantası.'],
            ['id' => 8, 'title' => 'Sahil Otoparkı', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9725, 'long' => 27.5178, 'status' => 'active', 'category_id' => 8, 'description' => 'Sahil bandına en yakın halka açık belediye otoparkı.'],
            ['id' => 9, 'title' => 'Çorlu Atatürk Parkı', 'province_id' => 59, 'district_id' => 2, 'lat' => 41.1592, 'long' => 27.802, 'status' => 'active', 'category_id' => 9, 'description' => 'Çorlu merkezindeki büyük şehir parkı; çocuk oyun alanları ve yürüyüş yolları.'],
            ['id' => 10, 'title' => 'Kent Lokantası Çorlu', 'province_id' => 59, 'district_id' => 2, 'lat' => 41.1571, 'long' => 27.7965, 'status' => 'active', 'category_id' => 7, 'description' => 'Belediyenin Çorlu’daki uygun fiyatlı kent lokantası.'],
            ['id' => 11, 'title' => 'Çorlu Kapalı Pazar', 'province_id' => 59, 'district_id' => 2, 'lat' => 41.1618, 'long' => 27.8103, 'status' => 'active', 'category_id' => 4, 'description' => 'Haftanın belirli günleri kurulan kapalı semt pazarı.'],
            ['id' => 12, 'title' => 'Çerkezköy Spor Salonu', 'province_id' => 59, 'district_id' => 3, 'lat' => 41.2831, 'long' => 27.9911, 'status' => 'active', 'category_id' => 5, 'description' => 'Kapalı spor salonu; basketbol, voleybol ve etkinlikler için kullanılır.'],
            ['id' => 13, 'title' => 'Çerkezköy Kültür Merkezi', 'province_id' => 59, 'district_id' => 3, 'lat' => 41.2854, 'long' => 27.9987, 'status' => 'active', 'category_id' => 3, 'description' => 'Tiyatro, konser ve sergilerin düzenlendiği ilçe kültür merkezi.'],
            ['id' => 14, 'title' => 'Şarköy Halk Plajı', 'province_id' => 59, 'district_id' => 4, 'lat' => 40.6103, 'long' => 27.1142, 'status' => 'active', 'category_id' => 2, 'description' => 'Marmara kıyısındaki ücretsiz halk plajı; yaz sezonunda cankurtaran hizmeti verilir.'],
            ['id' => 15, 'title' => 'Şarköy İskele Meydanı', 'province_id' => 59, 'district_id' => 4, 'lat' => 40.6119, 'long' => 27.1186, 'status' => 'active', 'category_id' => 6, 'description' => 'İlçenin buluşma noktası; iskele çevresinde kafeler ve etkinlik alanı.'],
            ['id' => 16, 'title' => 'Malkara Halk Kütüphanesi', 'province_id' => 59, 'district_id' => 5, 'lat' => 40.8901, 'long' => 27.5623, 'status' => 'active', 'category_id' => 3, 'description' => 'İlçe halk kütüphanesi; çalışma salonları ve çocuk bölümü mevcut.'],
            ['id' => 17, 'title' => 'Malkara Pazar Yeri', 'province_id' => 59, 'district_id' => 5, 'lat' => 40.8925, 'long' => 27.5568, 'status' => 'active', 'category_id' => 4, 'description' => 'İlçenin geleneksel açık pazar alanı.'],
            ['id' => 18, 'title' => 'Marmaraereğlisi Sahili', 'province_id' => 59, 'district_id' => 6, 'lat' => 40.9698, 'long' => 27.9552, 'status' => 'active', 'category_id' => 2, 'description' => 'Marmara kıyısı boyunca uzanan sahil ve yürüyüş bandı.'],
            ['id' => 19, 'title' => 'Kapaklı Millet Bahçesi', 'province_id' => 59, 'district_id' => 7, 'lat' => 41.3325, 'long' => 27.9754, 'status' => 'active', 'category_id' => 9, 'description' => 'Geniş yeşil alan; piknik ve spor alanlarıyla millet bahçesi.'],
            ['id' => 20, 'title' => 'Hayrabolu İlçe Stadyumu', 'province_id' => 59, 'district_id' => 8, 'lat' => 41.2124, 'long' => 27.1068, 'status' => 'active', 'category_id' => 5, 'description' => 'İlçe amatör liglerinin oynandığı stadyum.'],
            ['id' => 21, 'title' => 'Muratlı Tren Garı', 'province_id' => 59, 'district_id' => 9, 'lat' => 41.1742, 'long' => 27.5061, 'status' => 'active', 'category_id' => 10, 'description' => 'Muratlı ilçesindeki tarihi tren istasyonu.'],
            ['id' => 22, 'title' => 'Saray Yenice Göleti', 'province_id' => 59, 'district_id' => 10, 'lat' => 41.4421, 'long' => 27.9218, 'status' => 'active', 'category_id' => 9, 'description' => 'Doğa yürüyüşü ve piknik için tercih edilen gölet ve mesire alanı.'],
            ['id' => 23, 'title' => 'Ergene Hizmet Binası', 'province_id' => 59, 'district_id' => 11, 'lat' => 41.1803, 'long' => 27.7126, 'status' => 'active', 'category_id' => 1, 'description' => 'Büyükşehir belediyesinin Ergene ilçe hizmet birimi.'],
        ];
    }
}
