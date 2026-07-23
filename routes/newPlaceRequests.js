const express = require('express');
const prisma = require('../lib/prisma');
const requireAdmin = require('../lib/adminAuth');
const { requireAuth } = require('../lib/auth');
const { publicWriteLimiter } = require('../lib/rateLimit');
const { parsePositiveInt, isFiniteNumber, isNonEmptyString } = require('../lib/validate');
const { placesCache } = require('../lib/caches');

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
    include: { user: { select: { firstName: true, lastName: true, email: true } } },
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

  let resolvedDistrictId;
  let resolvedCategoryId;

  if (status === 'approved') {
    // gerçek bir Place'e dönüştürmek için districtId zorunlu (öneri formu district
    // toplamıyor), categoryId önerideyse ondan, yoksa body'den alınır.
    resolvedDistrictId = parsePositiveInt(districtId);
    if (resolvedDistrictId === null || resolvedDistrictId === undefined) {
      return res.status(400).json({ error: 'Onay için districtId zorunludur' });
    }

    resolvedCategoryId = submission.categoryId;
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
  }

  // Yukarıdaki findUnique ile buradaki update arasında başka bir istek de aynı
  // 'pending' öneriyi onaylayabilir (TOCTOU) — iki istek ikisi de status'ü pending
  // görüp ikisi de Place oluşturabilir, aynı öneriden çift kayıt çıkar. Bunu önlemek
  // için status geçişini WHERE'de status:'pending' şartıyla atomik yapıyoruz: sadece
  // bunu "kazanan" istek count=1 alır, diğeri count=0 görüp 409 döner.
  const result = await prisma.$transaction(async (tx) => {
    const claim = await tx.newPlaceSubmission.updateMany({
      where: { id, status: 'pending' },
      data: { status },
    });
    if (claim.count === 0) {
      return { conflict: true };
    }

    if (status === 'rejected') {
      const updated = await tx.newPlaceSubmission.findUnique({ where: { id } });
      return { updated };
    }

    const place = await tx.place.create({
      data: {
        districtId: resolvedDistrictId,
        categoryId: resolvedCategoryId,
        name: submission.name,
        latitude: submission.latitude,
        longitude: submission.longitude,
        description: submission.description,
      },
    });
    const updated = await tx.newPlaceSubmission.findUnique({ where: { id } });
    return { updated, place };
  });

  if (result.conflict) {
    return res.status(409).json({ error: `Bu öneri zaten karara bağlanmış` });
  }

  if (status === 'rejected') {
    return res.json(result.updated);
  }

  // Yeni Place eklendi; /places cache'i bayatlamasın diye hemen geçersiz kılınıyor.
  placesCache.invalidate();

  res.json({ submission: result.updated, place: result.place });
});

// POST /new-place-requests - giriş yapmış kullanıcı adına yeni yer önerisi gönder (durumu 'pending' olarak başlar)
router.post('/', requireAuth, publicWriteLimiter, async (req, res) => {
  const { name, categoryId, description, latitude, longitude } = req.body;

  if (!isNonEmptyString(name, 200)) {
    return res.status(400).json({ error: 'name zorunludur ve 200 karakteri geçemez' });
  }
  if (!isNonEmptyString(description)) {
    return res.status(400).json({ error: 'description zorunludur ve 2000 karakteri geçemez' });
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
      userId: req.userId,
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
