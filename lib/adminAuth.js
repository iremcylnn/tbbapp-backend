// Tek bir paylaşılan admin sırrı ile korunan basit endpoint koruması.
// Kullanıcı/rol sistemi yok; ihtiyaç büyürse gerçek bir auth mekanizmasına geçilebilir.
function requireAdmin(req, res, next) {
  const key = req.get('x-admin-key');
  if (!key || key !== process.env.ADMIN_API_KEY) {
    return res.status(401).json({ error: 'Yetkisiz' });
  }
  next();
}

module.exports = requireAdmin;
