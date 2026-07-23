const express = require('express');
const prisma = require('../lib/prisma');
const { districtsCache } = require('../lib/caches');

const router = express.Router();

// GET /districts - tüm ilçeleri listele (nadiren değiştiği için cache'lenir)
router.get('/', async (req, res) => {
  const districts = await districtsCache.get(() =>
    prisma.district.findMany({ orderBy: { name: 'asc' } })
  );
  res.json(districts);
});

module.exports = router;
