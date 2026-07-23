const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');

const SALT_ROUNDS = 10;
const TOKEN_EXPIRY = '30d';

function hashPassword(password) {
  return bcrypt.hash(password, SALT_ROUNDS);
}

function verifyPassword(password, hash) {
  return bcrypt.compare(password, hash);
}

function signToken(user) {
  return jwt.sign({ sub: user.id }, process.env.JWT_SECRET, { expiresIn: TOKEN_EXPIRY });
}

// Kayıt/giriş yapmış vatandaş kullanıcılar için — admin endpoint'lerindeki
// requireAdmin'den (lib/adminAuth.js) farklı bir kimlik doğrulama katmanı.
function requireAuth(req, res, next) {
  const header = req.get('authorization');
  if (!header || !header.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'Giriş gerekli' });
  }

  const token = header.slice('Bearer '.length);
  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET);
    req.userId = payload.sub;
    next();
  } catch {
    return res.status(401).json({ error: 'Geçersiz veya süresi dolmuş oturum' });
  }
}

module.exports = { hashPassword, verifyPassword, signToken, requireAuth };
