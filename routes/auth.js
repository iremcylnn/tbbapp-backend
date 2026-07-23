const bcrypt = require('bcryptjs');
const express = require('express');
const prisma = require('../lib/prisma');
const { hashPassword, verifyPassword, signToken } = require('../lib/auth');
const { publicWriteLimiter } = require('../lib/rateLimit');
const { isNonEmptyString, isValidEmail } = require('../lib/validate');

const router = express.Router();

// Kullanıcı bulunamadığında da bcrypt.compare'i gerçek bir hash'e karşı çalıştırmak için
// (aksi halde "email yok" yanıtı "email var ama şifre yanlış" yanıtından belirgin şekilde
// daha hızlı döner ve bu, kayıtlı email'leri timing'den ayırt etmeyi kolaylaştırır).
const DUMMY_HASH = bcrypt.hashSync('dummy-password-for-timing', 10);

function toPublicUser(user) {
  return { id: user.id, firstName: user.firstName, lastName: user.lastName, email: user.email };
}

// POST /auth/register - vatandaş kaydı oluşturur, girişte kullanılacak token'ı döner
router.post('/register', publicWriteLimiter, async (req, res) => {
  const { firstName, lastName, email, password } = req.body;

  if (!isNonEmptyString(firstName, 100) || !isNonEmptyString(lastName, 100)) {
    return res.status(400).json({ error: 'firstName ve lastName zorunludur' });
  }
  if (!isValidEmail(email)) {
    return res.status(400).json({ error: 'Geçerli bir email zorunludur' });
  }
  if (typeof password !== 'string' || password.length < 8) {
    return res.status(400).json({ error: 'password en az 8 karakter olmalıdır' });
  }

  const normalizedEmail = email.trim().toLowerCase();
  const existing = await prisma.user.findUnique({ where: { email: normalizedEmail } });
  if (existing) {
    return res.status(409).json({ error: 'Bu email zaten kayıtlı' });
  }

  const user = await prisma.user.create({
    data: {
      firstName: firstName.trim(),
      lastName: lastName.trim(),
      email: normalizedEmail,
      password: await hashPassword(password),
    },
  });

  res.status(201).json({ token: signToken(user), user: toPublicUser(user) });
});

// POST /auth/login - email + password ile giriş, token döner
router.post('/login', publicWriteLimiter, async (req, res) => {
  const { email, password } = req.body;

  if (!isValidEmail(email) || typeof password !== 'string' || !password) {
    return res.status(400).json({ error: 'email ve password zorunludur' });
  }

  const user = await prisma.user.findUnique({ where: { email: email.trim().toLowerCase() } });
  const passwordOk = await verifyPassword(password, user ? user.password : DUMMY_HASH);

  if (!user || !passwordOk) {
    return res.status(401).json({ error: 'Email veya şifre hatalı' });
  }

  res.json({ token: signToken(user), user: toPublicUser(user) });
});

module.exports = router;
