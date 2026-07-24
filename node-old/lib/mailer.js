const { Resend } = require('resend');

const resend = process.env.RESEND_API_KEY ? new Resend(process.env.RESEND_API_KEY) : null;

// RESEND_API_KEY tanımlı değilse (yerel geliştirme) gerçek e-posta atmak yerine kodu
// konsola yazar — akışın geri kalanını test etmeyi engellemez.
async function sendPasswordResetEmail(to, code) {
  if (!resend) {
    console.warn(`RESEND_API_KEY tanımlı değil; ${to} için sıfırlama kodu: ${code}`);
    return;
  }

  await resend.emails.send({
    from: process.env.MAIL_FROM || 'Tekirdağ Büyükşehir Belediyesi <onboarding@resend.dev>',
    to,
    subject: 'Şifre sıfırlama kodunuz',
    text: `Şifrenizi sıfırlamak için kodunuz: ${code}\n\nBu kod 15 dakika geçerlidir. Bu isteği siz yapmadıysanız bu e-postayı yok sayabilirsiniz.`,
  });
}

module.exports = { sendPasswordResetEmail };
