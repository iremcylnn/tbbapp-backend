const express = require('express');
const prisma = require('../lib/prisma');
const { parsePositiveInt, isFiniteNumber } = require('../lib/validate');

const router = express.Router();

// map.md: MapFeedbackSubmission.kind sadece 'complaint' | 'request' olabilir (Şikayet/Talep)
const VALID_KINDS = ['complaint', 'request'];

// POST /feedback - yeni şikayet/talep kaydet
router.post('/', async (req, res) => {
  const { kind, description, placeId, latitude, longitude } = req.body;

  if (!VALID_KINDS.includes(kind)) {
    return res.status(400).json({ error: `kind şunlardan biri olmalıdır: ${VALID_KINDS.join(', ')}` });
  }
  if (typeof description !== 'string' || !description.trim()) {
    return res.status(400).json({ error: 'description zorunludur' });
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
