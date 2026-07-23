const express = require('express');
const prisma = require('../lib/prisma');

const router = express.Router();

// GET /categories - tüm kategorileri listele
// map.md: MapCategory'de "order" alanı yok — sıralama dizinin kendi sırasıyla taşınır.
router.get('/', async (req, res) => {
  const categories = await prisma.category.findMany({
    select: { id: true, title: true },
    orderBy: { order: 'asc' },
  });
  res.json(categories);
});

module.exports = router;
