const rateLimit = require('express-rate-limit');

// Kimlik doğrulaması olmayan yazma endpoint'leri (POST /feedback, POST /new-place-requests)
// için spam/abuse koruması. Admin endpoint'lerine (GET/PATCH) uygulanmaz.
// Testler aynı IP'den art arda çok sayıda POST atıyor; test ortamında limiti gevşetiyoruz
// ki testler gerçek abuse korumasıyla değil kendi test verisiyle çakışmasın.
const publicWriteLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  limit: process.env.NODE_ENV === 'test' ? 1000 : 20,
  standardHeaders: true,
  legacyHeaders: false,
});

module.exports = { publicWriteLimiter };
