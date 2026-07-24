require('dotenv').config();

if (!process.env.DATABASE_URL) {
  console.error('HATA: DATABASE_URL ortam değişkeni tanımlı değil. .env dosyasını kontrol edin (bkz. .env.example).');
  process.exit(1);
}
if (!process.env.ADMIN_API_KEY) {
  console.error('HATA: ADMIN_API_KEY ortam değişkeni tanımlı değil. .env dosyasını kontrol edin (bkz. .env.example).');
  process.exit(1);
}
if (!process.env.JWT_SECRET) {
  console.error('HATA: JWT_SECRET ortam değişkeni tanımlı değil. .env dosyasını kontrol edin (bkz. .env.example).');
  process.exit(1);
}

const app = require('./app');
const prisma = require('./lib/prisma');

// Express 5, route handler'larındaki (sync/async) hataları zaten otomatik olarak
// error middleware'e yönlendiriyor — oradaki hatalar process'i çökertmez. Bu iki
// dinleyici, request-response döngüsünün DIŞINDA (ör. bir timer, bir kütüphane
// callback'i) oluşan gerçekten beklenmedik hatalara karşı son güvenlik ağı: process'i
// bozuk bir durumda sessizce çalışır bırakmak yerine loglayıp temiz şekilde kapatıyor.
// Gerçek dayanıklılık, bunu bir restart policy'yle (docker/pm2) çalıştırmaktan gelir.
process.on('unhandledRejection', (reason) => {
  throw reason;
});
process.on('uncaughtException', async (err) => {
  console.error('Beklenmedik hata, process kapatılıyor:', err);
  try {
    await prisma.$disconnect();
  } finally {
    process.exit(1);
  }
});

const port = process.env.PORT || 3000;
const server = app.listen(port, () => {
  console.log(`Sunucu http://localhost:${port} adresinde çalışıyor`);
});

async function shutdown(signal) {
  console.log(`${signal} alındı, kapatılıyor...`);
  server.close();
  await prisma.$disconnect();
  process.exit(0);
}
process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
