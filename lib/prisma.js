require('dotenv').config();
const { PrismaClient } = require('../generated/prisma');
const { PrismaPg } = require('@prisma/adapter-pg');

const adapter = new PrismaPg(
  {
    connectionString: process.env.DATABASE_URL,
    // Yoğun eşzamanlı trafikte havuzun tükenmemesi için üst sınır; tek Postgres
    // instance'ının varsayılan max_connections'ı (100) düşünülerek ayarlandı.
    max: Number(process.env.DB_POOL_MAX) || 20,
    idleTimeoutMillis: 30000,
    connectionTimeoutMillis: 5000,
  },
  {
    // node-postgres'in bilinen bir tuzağı: havuzdaki bir bağlantı hata verdiğinde
    // (ör. DB tarafından zaman aşımıyla kapatılan idle bağlantı) hiçbir 'error'
    // dinleyicisi yoksa Node bunu unhandled event olarak fırlatıp TÜM PROCESS'İ
    // ÇÖKERTİR. Bu dinleyiciler tam olarak bunu engelliyor — yoğun trafik altında
    // tek bir kopan bağlantı bütün sunucuyu düşürmesin diye.
    onPoolError: (err) => {
      console.error('Postgres havuz hatası:', err);
    },
    onConnectionError: (err) => {
      console.error('Postgres bağlantı hatası:', err);
    },
  }
);
const prisma = new PrismaClient({ adapter });

module.exports = prisma;
