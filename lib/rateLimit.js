const rateLimit = require('express-rate-limit');

// Kimlik doğrulaması olmayan yazma endpoint'leri (POST /feedback, POST /new-place-requests)
// için spam/abuse koruması. Admin endpoint'lerine (GET/PATCH) uygulanmaz.
const publicWriteLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  limit: 20,
  standardHeaders: true,
  legacyHeaders: false,
});

module.exports = { publicWriteLimiter };
