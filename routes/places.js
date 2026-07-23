const express = require('express');
const prisma = require('../lib/prisma');
const { parsePositiveInt } = require('../lib/validate');

const router = express.Router();

// GET /places?districtId=1&categoryId=2 - yerleri listele, isteğe bağlı filtrelerle
router.get('/', async (req, res) => {
  const { districtId, categoryId } = req.query;
  const where = {};

  if (districtId !== undefined) {
    const id = parsePositiveInt(districtId);
    if (id === null) return res.status(400).json({ error: 'Geçersiz districtId' });
    where.districtId = id;
  }

  if (categoryId !== undefined) {
    const id = parsePositiveInt(categoryId);
    if (id === null) return res.status(400).json({ error: 'Geçersiz categoryId' });
    where.categoryId = id;
  }

  const places = await prisma.place.findMany({
    where,
    orderBy: { id: 'asc' },
  });
  res.json(places);
});

// GET /places/:id - tek bir yerin detayını getir
router.get('/:id', async (req, res) => {
  const id = parsePositiveInt(req.params.id);
  if (id === null || id === undefined) {
    return res.status(400).json({ error: 'Geçersiz id' });
  }

  const place = await prisma.place.findUnique({ where: { id } });

  if (!place) {
    return res.status(404).json({ error: 'Yer bulunamadı' });
  }
  res.json(place);
});

module.exports = router;
