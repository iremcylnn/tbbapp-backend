const bcrypt = require('bcryptjs');
const crypto = require('crypto');
const express = require('express');
const prisma = require('../lib/prisma');
const { hashPassword, verifyPassword, signToken } = require('../lib/auth');
const { sendPasswordResetEmail } = require('../lib/mailer');
const { authLimiter } = require('../lib/rateLimit');
const { isNonEmptyString, isValidEmail } = require('../lib/validate');

const router = express.Router();

// Kullanıcı bulunamadığında da bcrypt.compare'i gerçek bir hash'e karşı çalıştırmak için
// (aksi halde "email yok" yanıtı "email var ama şifre yanlış" yanıtından belirgin şekilde
// daha hızlı döner ve bu, kayıtlı email'leri timing'den ayırt etmeyi kolaylaştırır).
const DUMMY_HASH = bcrypt.hashSync('dummy-password-for-timing', 10);

const RESET_CODE_TTL_MS = 15 * 60 * 1000;
const RESET_CODE_RE = /^\d{6}$/;

function generateResetCode() {
  return String(crypto.randomInt(0, 1000000)).padStart(6, '0');
}

function toPublicUser(user) {
  return { id: user.id, firstName: user.firstName, lastName: user.lastName, email: user.email };
}

// POST /auth/register - vatandaş kaydı oluşturur, girişte kullanılacak token'ı döner
router.post('/register', authLimiter, async (req, res) => {
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
router.post('/login', authLimiter, async (req, res) => {
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

// POST /auth/forgot-password - kayıtlıysa 6 haneli sıfırlama kodu üretip email'e gönderir.
// Email kayıtlı olsun ya da olmasın aynı yanıtı döner (enumeration'ı zorlaştırmak için,
// login'deki DUMMY_HASH ile aynı prensip).
router.post('/forgot-password', authLimiter, async (req, res) => {
  const { email } = req.body;
  if (!isValidEmail(email)) {
    return res.status(400).json({ error: 'Geçerli bir email zorunludur' });
  }

  const user = await prisma.user.findUnique({ where: { email: email.trim().toLowerCase() } });
  if (user) {
    const code = generateResetCode();
    // Önceki isteklerden kalan, hâlâ süresi dolmamış kodları geçersiz kılıyoruz — aksi halde
    // art arda "kodu göndermedi, tekrar dene" ile aynı kullanıcı için birden çok kod aynı anda
    // geçerli kalır: reset-password her denemede hepsine karşı bcrypt.compare koşturmak zorunda
    // kalır (gereksiz CPU yükü) ve eski/unutulmuş bir kod gereğinden uzun süre kullanılabilir kalır.
    await prisma.passwordResetCode.updateMany({
      where: { userId: user.id, usedAt: null, expiresAt: { gt: new Date() } },
      data: { usedAt: new Date() },
    });
    await prisma.passwordResetCode.create({
      data: {
        userId: user.id,
        codeHash: await hashPassword(code),
        expiresAt: new Date(Date.now() + RESET_CODE_TTL_MS),
      },
    });
    sendPasswordResetEmail(user.email, code).catch((err) =>
      console.error('Şifre sıfırlama e-postası gönderilemedi:', err),
    );
  }

  res.json({ message: 'Bu email kayıtlıysa bir sıfırlama kodu gönderildi' });
});

// POST /auth/reset-password - email + kod + yeni şifre ile şifreyi günceller
router.post('/reset-password', authLimiter, async (req, res) => {
  const { email, code, password } = req.body;

  if (!isValidEmail(email) || typeof code !== 'string' || !RESET_CODE_RE.test(code)) {
    return res.status(400).json({ error: 'Kod geçersiz veya süresi dolmuş' });
  }
  if (typeof password !== 'string' || password.length < 8) {
    return res.status(400).json({ error: 'password en az 8 karakter olmalıdır' });
  }

  const user = await prisma.user.findUnique({ where: { email: email.trim().toLowerCase() } });
  const candidates = user
    ? await prisma.passwordResetCode.findMany({
        where: { userId: user.id, usedAt: null, expiresAt: { gt: new Date() } },
        orderBy: { createdAt: 'desc' },
      })
    : [];

  let matched = null;
  for (const candidate of candidates) {
    if (await verifyPassword(code, candidate.codeHash)) {
      matched = candidate;
      break;
    }
  }

  if (!matched) {
    return res.status(400).json({ error: 'Kod geçersiz veya süresi dolmuş' });
  }

  await prisma.$transaction([
    prisma.user.update({ where: { id: user.id }, data: { password: await hashPassword(password) } }),
    prisma.passwordResetCode.update({ where: { id: matched.id }, data: { usedAt: new Date() } }),
  ]);

  res.json({ message: 'Şifre güncellendi' });
});

module.exports = router;
