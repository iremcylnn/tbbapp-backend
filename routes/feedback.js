const express = require('express');
const prisma = require('../lib/prisma');
const requireAdmin = require('../lib/adminAuth');
const { requireAuth } = require('../lib/auth');
const { publicWriteLimiter } = require('../lib/rateLimit');
const { parsePositiveInt, isFiniteNumber, isNonEmptyString } = require('../lib/validate');

const router = express.Router();

// map.md: MapFeedbackSubmission.kind sadece 'complaint' | 'request' olabilir (Şikayet/Talep)
const VALID_KINDS = ['complaint', 'request'];

// GET /feedback?kind=complaint - şikayet/talepleri listele (admin)
router.get('/', requireAdmin, async (req, res) => {
  const { kind } = req.query;
  const where = {};

  if (kind !== undefined) {
    if (!VALID_KINDS.includes(kind)) {
      return res.status(400).json({ error: `kind şunlardan biri olmalıdır: ${VALID_KINDS.join(', ')}` });
    }
    where.kind = kind;
  }

  const feedback = await prisma.feedbackSubmission.findMany({
    where,
    orderBy: { createdAt: 'desc' },
    include: { user: { select: { firstName: true, lastName: true, email: true } } },
  });
  res.json(feedback);
});

// POST /feedback - giriş yapmış kullanıcı adına yeni şikayet/talep kaydet
router.post('/', requireAuth, publicWriteLimiter, async (req, res) => {
  const { kind, description, placeId, latitude, longitude } = req.body;

  if (!VALID_KINDS.includes(kind)) {
    return res.status(400).json({ error: `kind şunlardan biri olmalıdır: ${VALID_KINDS.join(', ')}` });
  }
  if (!isNonEmptyString(description)) {
    return res.status(400).json({ error: 'description zorunludur ve 2000 karakteri geçemez' });
  }

  let resolvedPlaceId = null;
  if (placeId !== undefined && placeId !== null) {
    const id = parsePositiveInt(placeId);
    if (id === null || id === undefined) return res.status(400).json({ error: 'Geçersiz placeId' });
    resolvedPlaceId = id;
  }

  if (latitude !== undefined && latitude !== null && !isFiniteNumber(latitude)) {
    return res.status(400).json({ error: 'latitude sayısal olmalıdır' });
  }
  if (longitude !== undefined && longitude !== null && !isFiniteNumber(longitude)) {
    return res.status(400).json({ error: 'longitude sayısal olmalıdır' });
  }

  const feedback = await prisma.feedbackSubmission.create({
    data: {
      userId: req.userId,
      kind,
      description: description.trim(),
      placeId: resolvedPlaceId,
      latitude: latitude ?? null,
      longitude: longitude ?? null,
    },
  });
  res.status(201).json(feedback);
});

module.exports = router;
