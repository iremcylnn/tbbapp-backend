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
 *
 * District ids are the well-known Tekirdağ list (1=Süleymanpaşa, 2=Çorlu,
 * 3=Çerkezköy, 4=Şarköy, 5=Malkara, 6=Marmaraereğlisi, 7=Kapaklı,
 * 8=Hayrabolu, 9=Muratlı, 10=Saray, 11=Ergene). A districts table awaits the
 * guide's decision, so they stay plain integers.
 */
class MapSeedData
{
    /**
     * Freshness marker for the mock source. Static data → a constant → the
     * mock ETag never rotates. Bump by one whenever this dataset is edited.
     */
    public const VERSION = 1;

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
     * @return list<array{id: int, title: string, province_id: int, district_id: int, lat: float, long: float, status: string, category_id: int}>
     */
    public static function places(): array
    {
        return [
            ['id' => 1, 'title' => 'Tekirdağ Büyükşehir Belediyesi', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9778, 'long' => 27.5147, 'status' => 'active', 'category_id' => 1],
            ['id' => 2, 'title' => 'Süleymanpaşa Sahil', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.974, 'long' => 27.516, 'status' => 'active', 'category_id' => 2],
            ['id' => 3, 'title' => 'Rakoczi Müzesi', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9731, 'long' => 27.5112, 'status' => 'active', 'category_id' => 3],
            ['id' => 4, 'title' => 'Tekira AVM', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.982, 'long' => 27.513, 'status' => 'active', 'category_id' => 4],
            ['id' => 5, 'title' => 'Namık Kemal Stadyumu', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.985, 'long' => 27.505, 'status' => 'active', 'category_id' => 5],
            ['id' => 6, 'title' => '100. Yıl Gençlik Merkezi', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9805, 'long' => 27.519, 'status' => 'active', 'category_id' => 6],
            ['id' => 7, 'title' => 'Kent Lokantası Süleymanpaşa', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9762, 'long' => 27.5089, 'status' => 'active', 'category_id' => 7],
            ['id' => 8, 'title' => 'Sahil Otoparkı', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9725, 'long' => 27.5178, 'status' => 'active', 'category_id' => 8],
            ['id' => 9, 'title' => 'Çorlu Atatürk Parkı', 'province_id' => 59, 'district_id' => 2, 'lat' => 41.1592, 'long' => 27.802, 'status' => 'active', 'category_id' => 9],
            ['id' => 10, 'title' => 'Kent Lokantası Çorlu', 'province_id' => 59, 'district_id' => 2, 'lat' => 41.1571, 'long' => 27.7965, 'status' => 'active', 'category_id' => 7],
            ['id' => 11, 'title' => 'Çorlu Kapalı Pazar', 'province_id' => 59, 'district_id' => 2, 'lat' => 41.1618, 'long' => 27.8103, 'status' => 'active', 'category_id' => 4],
            ['id' => 12, 'title' => 'Çerkezköy Spor Salonu', 'province_id' => 59, 'district_id' => 3, 'lat' => 41.2831, 'long' => 27.9911, 'status' => 'active', 'category_id' => 5],
            ['id' => 13, 'title' => 'Çerkezköy Kültür Merkezi', 'province_id' => 59, 'district_id' => 3, 'lat' => 41.2854, 'long' => 27.9987, 'status' => 'active', 'category_id' => 3],
            ['id' => 14, 'title' => 'Şarköy Halk Plajı', 'province_id' => 59, 'district_id' => 4, 'lat' => 40.6103, 'long' => 27.1142, 'status' => 'active', 'category_id' => 2],
            ['id' => 15, 'title' => 'Şarköy İskele Meydanı', 'province_id' => 59, 'district_id' => 4, 'lat' => 40.6119, 'long' => 27.1186, 'status' => 'active', 'category_id' => 6],
            ['id' => 16, 'title' => 'Malkara Halk Kütüphanesi', 'province_id' => 59, 'district_id' => 5, 'lat' => 40.8901, 'long' => 27.5623, 'status' => 'active', 'category_id' => 3],
            ['id' => 17, 'title' => 'Malkara Pazar Yeri', 'province_id' => 59, 'district_id' => 5, 'lat' => 40.8925, 'long' => 27.5568, 'status' => 'active', 'category_id' => 4],
            ['id' => 18, 'title' => 'Marmaraereğlisi Sahili', 'province_id' => 59, 'district_id' => 6, 'lat' => 40.9698, 'long' => 27.9552, 'status' => 'active', 'category_id' => 2],
            ['id' => 19, 'title' => 'Kapaklı Millet Bahçesi', 'province_id' => 59, 'district_id' => 7, 'lat' => 41.3325, 'long' => 27.9754, 'status' => 'active', 'category_id' => 9],
            ['id' => 20, 'title' => 'Hayrabolu İlçe Stadyumu', 'province_id' => 59, 'district_id' => 8, 'lat' => 41.2124, 'long' => 27.1068, 'status' => 'active', 'category_id' => 5],
            ['id' => 21, 'title' => 'Muratlı Tren Garı', 'province_id' => 59, 'district_id' => 9, 'lat' => 41.1742, 'long' => 27.5061, 'status' => 'active', 'category_id' => 10],
            ['id' => 22, 'title' => 'Saray Yenice Göleti', 'province_id' => 59, 'district_id' => 10, 'lat' => 41.4421, 'long' => 27.9218, 'status' => 'active', 'category_id' => 9],
            ['id' => 23, 'title' => 'Ergene Hizmet Binası', 'province_id' => 59, 'district_id' => 11, 'lat' => 41.1803, 'long' => 27.7126, 'status' => 'active', 'category_id' => 1],
        ];
    }
}
