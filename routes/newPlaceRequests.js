const express = require('express');
const prisma = require('../lib/prisma');
const { parsePositiveInt, isFiniteNumber } = require('../lib/validate');

const router = express.Router();

// POST /new-place-requests - yeni yer önerisi gönder (durumu 'pending' olarak başlar)
router.post('/', async (req, res) => {
  const { name, categoryId, description, latitude, longitude } = req.body;

  if (typeof name !== 'string' || !name.trim() || typeof description !== 'string' || !description.trim()) {
    return res.status(400).json({ error: 'name ve description zorunludur' });
  }

  if (!isFiniteNumber(latitude) || !isFiniteNumber(longitude)) {
    return res.status(400).json({ error: 'latitude ve longitude sayısal olmalıdır' });
  }

  let resolvedCategoryId = null;
  if (categoryId !== undefined && categoryId !== null) {
    const id = parsePositiveInt(categoryId);
    if (id === null || id === undefined) return res.status(400).json({ error: 'Geçersiz categoryId' });
    resolvedCategoryId = id;
  }

  const request = await prisma.newPlaceSubmission.create({
    data: {
      name: name.trim(),
      categoryId: resolvedCategoryId,
      description: description.trim(),
      latitude,
      longitude,
    },
  });
  res.status(201).json(request);
});

module.exports = router;
