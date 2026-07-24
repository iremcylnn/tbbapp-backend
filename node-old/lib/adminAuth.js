const crypto = require('crypto');

// Tek bir paylaşılan admin sırrı ile korunan basit endpoint koruması.
// Kullanıcı/rol sistemi yok; ihtiyaç büyürse gerçek bir auth mekanizmasına geçilebilir.
function requireAdmin(req, res, next) {
  const key = req.get('x-admin-key');
  const expected = process.env.ADMIN_API_KEY;

  // Basit '!==' karşılaştırması karakter karakter kısa devre yapar ve teoride
  // yanıt süresinden doğru key'in ne kadarının tutturulduğunu sızdırabilir
  // (timing attack). timingSafeEqual sabit zamanlı karşılaştırır; uzunluklar
  // farklıysa (ki genelde öyle olur) önce bunu ayrı kontrol ediyoruz çünkü
  // fonksiyon farklı uzunluktaki buffer'larda hata fırlatıyor.
  const keyBuffer = Buffer.from(key ?? '');
  const expectedBuffer = Buffer.from(expected ?? '');
  const matches =
    keyBuffer.length === expectedBuffer.length && crypto.timingSafeEqual(keyBuffer, expectedBuffer);

  if (!key || !matches) {
    return res.status(401).json({ error: 'Yetkisiz' });
  }
  next();
}

module.exports = requireAdmin;
