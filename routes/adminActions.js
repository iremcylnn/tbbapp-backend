const express = require('express');
const prisma = require('../lib/prisma');
const requireAdmin = require('../lib/adminAuth');

const router = express.Router();

// GET /admin-actions?targetType=NewPlaceSubmission&targetId=5 - admin eylem kayıtlarını listele.
// Paylaşılan x-admin-key gerçek admin kimliği taşımadığı için "kim" değil, "ne zaman + hangi
// IP'den + ne yapıldı" bilgisini verir (bkz. schema.prisma'daki AdminActionLog yorumu).
router.get('/', requireAdmin, async (req, res) => {
  const { targetType, targetId } = req.query;
  const where = {};

  if (targetType !== undefined) {
    where.targetType = targetType;
  }
  if (targetId !== undefined) {
    const id = Number(targetId);
    if (!Number.isInteger(id) || id <= 0) {
      return res.status(400).json({ error: 'Geçersiz targetId' });
    }
    where.targetId = id;
  }

  const logs = await prisma.adminActionLog.findMany({
    where,
    orderBy: { createdAt: 'desc' },
  });
  res.json(logs);
});

module.exports = router;
