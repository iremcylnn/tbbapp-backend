require('dotenv').config();
const { PrismaClient } = require('../generated/prisma');
const { PrismaPg } = require('@prisma/adapter-pg');

const adapter = new PrismaPg({ connectionString: process.env.DATABASE_URL });
const prisma = new PrismaClient({ adapter });

const districts = [
  { id: 1, name: 'Süleymanpaşa' },
  { id: 2, name: 'Çorlu' },
  { id: 3, name: 'Çerkezköy' },
  { id: 4, name: 'Şarköy' },
  { id: 5, name: 'Malkara' },
  { id: 6, name: 'Marmaraereğlisi' },
  { id: 7, name: 'Kapaklı' },
  { id: 8, name: 'Hayrabolu' },
  { id: 9, name: 'Muratlı' },
  { id: 10, name: 'Saray' },
  { id: 11, name: 'Ergene' },
];

const categories = [
  { id: 1, title: 'Belediye', order: 1 },
  { id: 2, title: 'Sahil', order: 2 },
  { id: 3, title: 'Kültür', order: 3 },
  { id: 4, title: 'Alışveriş', order: 4 },
  { id: 5, title: 'Spor', order: 5 },
  { id: 6, title: 'Toplum', order: 6 },
  { id: 7, title: 'Kent Lokantası', order: 7 },
  { id: 8, title: 'Otopark', order: 8 },
  { id: 9, title: 'Park', order: 9 },
  { id: 10, title: 'Ulaşım', order: 10 },
];

const places = [
  { id: 1, districtId: 1, categoryId: 1, name: 'Tekirdağ Büyükşehir Belediyesi', description: 'Büyükşehir belediyesinin ana hizmet binası; nikah, ruhsat ve genel başvuru işlemleri burada yürütülür.', latitude: 40.9778, longitude: 27.5147 },
  { id: 2, districtId: 1, categoryId: 2, name: 'Süleymanpaşa Sahil', description: 'Kent merkezinin yürüyüş ve dinlenme alanı; sahil bandı boyunca kafeler ve oturma alanları bulunur.', latitude: 40.974, longitude: 27.516 },
  { id: 3, districtId: 1, categoryId: 3, name: 'Rakoczi Müzesi', description: 'Macar prensi II. Ferenc Rakoczi’nin sürgün yıllarını geçirdiği ev; Türk-Macar dostluğunun simgesi.', latitude: 40.9731, longitude: 27.5112 },
  { id: 4, districtId: 1, categoryId: 4, name: 'Tekira AVM', description: 'Şehrin en büyük alışveriş merkezi; mağazalar, sinema ve yemek katı.', latitude: 40.982, longitude: 27.513 },
  { id: 5, districtId: 1, categoryId: 5, name: 'Namık Kemal Stadyumu', description: 'Tekirdağspor’un iç saha maçlarını oynadığı şehir stadyumu.', latitude: 40.985, longitude: 27.505 },
  { id: 6, districtId: 1, categoryId: 6, name: '100. Yıl Gençlik Merkezi', description: 'Gençlere yönelik kurslar, atölyeler ve etkinliklerin düzenlendiği merkez.', latitude: 40.9805, longitude: 27.519 },
  { id: 7, districtId: 1, categoryId: 7, name: 'Kent Lokantası Süleymanpaşa', description: 'Belediyenin uygun fiyatlı yemek hizmeti sunduğu kent lokantası.', latitude: 40.9762, longitude: 27.5089 },
  { id: 8, districtId: 1, categoryId: 8, name: 'Sahil Otoparkı', description: 'Sahil bandına en yakın halka açık belediye otoparkı.', latitude: 40.9725, longitude: 27.5178 },
  { id: 9, districtId: 2, categoryId: 9, name: 'Çorlu Atatürk Parkı', description: 'Çorlu merkezindeki büyük şehir parkı; çocuk oyun alanları ve yürüyüş yolları.', latitude: 41.1592, longitude: 27.802 },
  { id: 10, districtId: 2, categoryId: 7, name: 'Kent Lokantası Çorlu', description: 'Belediyenin Çorlu’daki uygun fiyatlı kent lokantası.', latitude: 41.1571, longitude: 27.7965 },
  { id: 11, districtId: 2, categoryId: 4, name: 'Çorlu Kapalı Pazar', description: 'Haftanın belirli günleri kurulan kapalı semt pazarı.', latitude: 41.1618, longitude: 27.8103 },
  { id: 12, districtId: 3, categoryId: 5, name: 'Çerkezköy Spor Salonu', description: 'Kapalı spor salonu; basketbol, voleybol ve etkinlikler için kullanılır.', latitude: 41.2831, longitude: 27.9911 },
  { id: 13, districtId: 3, categoryId: 3, name: 'Çerkezköy Kültür Merkezi', description: 'Tiyatro, konser ve sergilerin düzenlendiği ilçe kültür merkezi.', latitude: 41.2854, longitude: 27.9987 },
  { id: 14, districtId: 4, categoryId: 2, name: 'Şarköy Halk Plajı', description: 'Marmara kıyısındaki ücretsiz halk plajı; yaz sezonunda cankurtaran hizmeti verilir.', latitude: 40.6103, longitude: 27.1142 },
  { id: 15, districtId: 4, categoryId: 6, name: 'Şarköy İskele Meydanı', description: 'İlçenin buluşma noktası; iskele çevresinde kafeler ve etkinlik alanı.', latitude: 40.6119, longitude: 27.1186 },
  { id: 16, districtId: 5, categoryId: 3, name: 'Malkara Halk Kütüphanesi', description: 'İlçe halk kütüphanesi; çalışma salonları ve çocuk bölümü mevcut.', latitude: 40.8901, longitude: 27.5623 },
  { id: 17, districtId: 5, categoryId: 4, name: 'Malkara Pazar Yeri', description: 'İlçenin geleneksel açık pazar alanı.', latitude: 40.8925, longitude: 27.5568 },
  { id: 18, districtId: 6, categoryId: 2, name: 'Marmaraereğlisi Sahili', description: 'Marmara kıyısı boyunca uzanan sahil ve yürüyüş bandı.', latitude: 40.9698, longitude: 27.9552 },
  { id: 19, districtId: 7, categoryId: 9, name: 'Kapaklı Millet Bahçesi', description: 'Geniş yeşil alan; piknik ve spor alanlarıyla millet bahçesi.', latitude: 41.3325, longitude: 27.9754 },
  { id: 20, districtId: 8, categoryId: 5, name: 'Hayrabolu İlçe Stadyumu', description: 'İlçe amatör liglerinin oynandığı stadyum.', latitude: 41.2124, longitude: 27.1068 },
  { id: 21, districtId: 9, categoryId: 10, name: 'Muratlı Tren Garı', description: 'Muratlı ilçesindeki tarihi tren istasyonu.', latitude: 41.1742, longitude: 27.5061 },
  { id: 22, districtId: 10, categoryId: 9, name: 'Saray Yenice Göleti', description: 'Doğa yürüyüşü ve piknik için tercih edilen gölet ve mesire alanı.', latitude: 41.4421, longitude: 27.9218 },
  { id: 23, districtId: 11, categoryId: 1, name: 'Ergene Hizmet Binası', description: 'Büyükşehir belediyesinin Ergene ilçe hizmet birimi.', latitude: 41.1803, longitude: 27.7126 },
];

async function main() {
  for (const d of districts) {
    await prisma.district.upsert({ where: { id: d.id }, update: {}, create: d });
  }
  for (const c of categories) {
    await prisma.category.upsert({ where: { id: c.id }, update: {}, create: c });
  }
  for (const p of places) {
    await prisma.place.upsert({ where: { id: p.id }, update: {}, create: p });
  }

  // Explicit ids were inserted above; bump each table's auto-increment counter
  // past them so future inserts without an id don't collide.
  await prisma.$executeRawUnsafe(`SELECT setval(pg_get_serial_sequence('ilceler','id'), (SELECT MAX(id) FROM ilceler))`);
  await prisma.$executeRawUnsafe(`SELECT setval(pg_get_serial_sequence('harita_filtre','id'), (SELECT MAX(id) FROM harita_filtre))`);
  await prisma.$executeRawUnsafe(`SELECT setval(pg_get_serial_sequence('koordinatlar','id'), (SELECT MAX(id) FROM koordinatlar))`);

  console.log(`Seed tamam: ${districts.length} ilçe, ${categories.length} kategori, ${places.length} yer eklendi.`);
}

main()
  .then(() => prisma.$disconnect())
  .catch(async (e) => {
    console.error(e);
    await prisma.$disconnect();
    process.exit(1);
  });
