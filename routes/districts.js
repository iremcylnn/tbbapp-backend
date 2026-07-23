const express = require('express');
const prisma = require('../lib/prisma');

const router = express.Router();

// GET /districts - tüm ilçeleri listele
router.get('/', async (req, res) => {
  const districts = await prisma.district.findMany({ orderBy: { name: 'asc' } });
  res.json(districts);
});

module.exports = router;
