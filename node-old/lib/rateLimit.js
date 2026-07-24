const rateLimit = require('express-rate-limit');

// Testler aynı IP'den art arda çok sayıda POST atıyor; test ortamında limiti gevşetiyoruz
// ki testler gerçek abuse korumasıyla değil kendi test verisiyle çakışmasın.
const WINDOW_MS = 15 * 60 * 1000;
const LIMIT = process.env.NODE_ENV === 'test' ? 1000 : 20;

// Giriş yapmış kullanıcının yazma endpoint'leri (POST /feedback, POST /new-place-requests)
// için spam/abuse koruması. Admin endpoint'lerine (GET/PATCH) uygulanmaz.
const publicWriteLimiter = rateLimit({
  windowMs: WINDOW_MS,
  limit: LIMIT,
  standardHeaders: true,
  legacyHeaders: false,
});

// /auth/* uçları (register/login/forgot-password/reset-password) için AYRI bir limiter —
// publicWriteLimiter'ı paylaşırlarsa (express-rate-limit'te aynı middleware instance'ı = aynı
// paylaşımlı sayaç) birkaç yanlış şifre denemesi, alakasız şekilde şikayet/yeni-yer gönderme
// hakkını da tüketir (ya da tam tersi) — ikisi birbirinden habersiz iki farklı tehdit modeli.
const authLimiter = rateLimit({
  windowMs: WINDOW_MS,
  limit: LIMIT,
  standardHeaders: true,
  legacyHeaders: false,
});

module.exports = { publicWriteLimiter, authLimiter };
