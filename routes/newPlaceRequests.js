const express = require('express');
const prisma = require('../lib/prisma');
const requireAdmin = require('../lib/adminAuth');
const { publicWriteLimiter } = require('../lib/rateLimit');
const { parsePositiveInt, isFiniteNumber } = require('../lib/validate');

const router = express.Router();

const VALID_STATUSES = ['pending', 'approved', 'rejected'];

// GET /new-place-requests?status=pending - yeni yer önerilerini listele (admin)
router.get('/', requireAdmin, async (req, res) => {
  const { status } = req.query;
  const where = {};

  if (status !== undefined) {
    if (!VALID_STATUSES.includes(status)) {
      return res.status(400).json({ error: `status şunlardan biri olmalıdır: ${VALID_STATUSES.join(', ')}` });
    }
    where.status = status;
  }

  const requests = await prisma.newPlaceSubmission.findMany({
    where,
    orderBy: { createdAt: 'desc' },
  });
  res.json(requests);
});

// PATCH /new-place-requests/:id - öneriyi onayla (gerçek bir Place oluşturur) veya reddet (admin)
router.patch('/:id', requireAdmin, async (req, res) => {
  const id = parsePositiveInt(req.params.id);
  if (id === null || id === undefined) {
    return res.status(400).json({ error: 'Geçersiz id' });
  }

  const { status, districtId, categoryId } = req.body;
  if (status !== 'approved' && status !== 'rejected') {
    return res.status(400).json({ error: "status 'approved' veya 'rejected' olmalıdır" });
  }

  const submission = await prisma.newPlaceSubmission.findUnique({ where: { id } });
  if (!submission) {
    return res.status(404).json({ error: 'Öneri bulunamadı' });
  }
  if (submission.status !== 'pending') {
    return res.status(409).json({ error: `Bu öneri zaten '${submission.status}' durumunda` });
  }

  if (status === 'rejected') {
    const updated = await prisma.newPlaceSubmission.update({
      where: { id },
      data: { status: 'rejected' },
    });
    return res.json(updated);
  }

  // status === 'approved': gerçek bir Place'e dönüştürmek için districtId zorunlu
  // (öneri formu district toplamıyor), categoryId önerideyse ondan, yoksa body'den alınır.
  const resolvedDistrictId = parsePositiveInt(districtId);
  if (resolvedDistrictId === null || resolvedDistrictId === undefined) {
    return res.status(400).json({ error: 'Onay için districtId zorunludur' });
  }

  let resolvedCategoryId = submission.categoryId;
  if (categoryId !== undefined && categoryId !== null) {
    const id2 = parsePositiveInt(categoryId);
    if (id2 === null || id2 === undefined) return res.status(400).json({ error: 'Geçersiz categoryId' });
    resolvedCategoryId = id2;
  }
  if (!resolvedCategoryId) {
    return res.status(400).json({ error: 'Onay için categoryId zorunludur (öneride yoksa body ile gönderin)' });
  }

  const [district, category] = await Promise.all([
    prisma.district.findUnique({ where: { id: resolvedDistrictId } }),
    prisma.category.findUnique({ where: { id: resolvedCategoryId } }),
  ]);
  if (!district) return res.status(400).json({ error: 'Geçersiz districtId' });
  if (!category) return res.status(400).json({ error: 'Geçersiz categoryId' });

  const [place, updatedSubmission] = await prisma.$transaction([
    prisma.place.create({
      data: {
        districtId: resolvedDistrictId,
        categoryId: resolvedCategoryId,
        name: submission.name,
        latitude: submission.latitude,
        longitude: submission.longitude,
        description: submission.description,
      },
    }),
    prisma.newPlaceSubmission.update({
      where: { id },
      data: { status: 'approved' },
    }),
  ]);

  res.json({ submission: updatedSubmission, place });
});

// POST /new-place-requests - yeni yer önerisi gönder (durumu 'pending' olarak başlar)
router.post('/', publicWriteLimiter, async (req, res) => {
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
