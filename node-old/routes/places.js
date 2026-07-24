const express = require('express');
const prisma = require('../lib/prisma');
const { parsePositiveInt } = require('../lib/validate');
const { placesCache } = require('../lib/caches');

const router = express.Router();

function loadAllPlaces() {
  return placesCache.get(() => prisma.place.findMany({ orderBy: { id: 'asc' } }));
}

// GET /places?districtId=1&categoryId=2 - yerleri listele, isteğe bağlı filtrelerle
// Tüm liste cache'den okunur, filtreleme DB'ye gitmeden bellekte yapılır — veri seti
// küçük olduğu için (şehir ölçeğinde yüzlerce satır) bu, yoğun trafikte DB'yi korur.
router.get('/', async (req, res) => {
  const { districtId, categoryId } = req.query;
  let filterDistrictId;
  let filterCategoryId;

  if (districtId !== undefined) {
    filterDistrictId = parsePositiveInt(districtId);
    if (filterDistrictId === null) return res.status(400).json({ error: 'Geçersiz districtId' });
  }
  if (categoryId !== undefined) {
    filterCategoryId = parsePositiveInt(categoryId);
    if (filterCategoryId === null) return res.status(400).json({ error: 'Geçersiz categoryId' });
  }

  const allPlaces = await loadAllPlaces();
  const places = allPlaces.filter((p) => {
    if (filterDistrictId !== undefined && p.districtId !== filterDistrictId) return false;
    if (filterCategoryId !== undefined && p.categoryId !== filterCategoryId) return false;
    return true;
  });

  res.json(places);
});

// GET /places/:id - tek bir yerin detayını getir
router.get('/:id', async (req, res) => {
  const id = parsePositiveInt(req.params.id);
  if (id === null || id === undefined) {
    return res.status(400).json({ error: 'Geçersiz id' });
  }

  const allPlaces = await loadAllPlaces();
  const place = allPlaces.find((p) => p.id === id);

  if (!place) {
    return res.status(404).json({ error: 'Yer bulunamadı' });
  }
  res.json(place);
});

module.exports = router;
