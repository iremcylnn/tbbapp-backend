jest.mock('../lib/mailer', () => ({
  sendPasswordResetEmail: jest.fn().mockResolvedValue(undefined),
}));

const request = require('supertest');
const app = require('../app');
const prisma = require('../lib/prisma');
const { sendPasswordResetEmail } = require('../lib/mailer');

const createdUserIds = [];

afterAll(async () => {
  if (createdUserIds.length) {
    await prisma.passwordResetCode.deleteMany({ where: { userId: { in: createdUserIds } } });
    await prisma.user.deleteMany({ where: { id: { in: createdUserIds } } });
  }
  await prisma.$disconnect();
});

function uniqueEmail() {
  return `test-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com`;
}

describe('POST /auth/register', () => {
  it('geçerli veriyle 201 döner, token ve kullanıcı bilgisi içerir', async () => {
    const email = uniqueEmail();
    const res = await request(app)
      .post('/auth/register')
      .send({ firstName: 'Ayşe', lastName: 'Yılmaz', email, password: 'sifre1234' });

    expect(res.status).toBe(201);
    expect(res.body.token).toEqual(expect.any(String));
    expect(res.body.user).toEqual(
      expect.objectContaining({ firstName: 'Ayşe', lastName: 'Yılmaz', email })
    );
    expect(res.body.user.password).toBeUndefined();
    createdUserIds.push(res.body.user.id);
  });

  it('aynı email ile ikinci kayıt 409 döner', async () => {
    const email = uniqueEmail();
    const first = await request(app)
      .post('/auth/register')
      .send({ firstName: 'A', lastName: 'B', email, password: 'sifre1234' });
    createdUserIds.push(first.body.user.id);

    const res = await request(app)
      .post('/auth/register')
      .send({ firstName: 'C', lastName: 'D', email, password: 'baskasifre' });

    expect(res.status).toBe(409);
  });

  it('kısa şifre ile 400 döner', async () => {
    const res = await request(app)
      .post('/auth/register')
      .send({ firstName: 'A', lastName: 'B', email: uniqueEmail(), password: '123' });

    expect(res.status).toBe(400);
  });

  it('geçersiz email ile 400 döner', async () => {
    const res = await request(app)
      .post('/auth/register')
      .send({ firstName: 'A', lastName: 'B', email: 'gecersiz-email', password: 'sifre1234' });

    expect(res.status).toBe(400);
  });
});

describe('POST /auth/login', () => {
  it('doğru bilgilerle giriş yapar, token döner', async () => {
    const email = uniqueEmail();
    const registerRes = await request(app)
      .post('/auth/register')
      .send({ firstName: 'A', lastName: 'B', email, password: 'sifre1234' });
    createdUserIds.push(registerRes.body.user.id);

    const res = await request(app).post('/auth/login').send({ email, password: 'sifre1234' });

    expect(res.status).toBe(200);
    expect(res.body.token).toEqual(expect.any(String));
  });

  it('yanlış şifre ile 401 döner', async () => {
    const email = uniqueEmail();
    const registerRes = await request(app)
      .post('/auth/register')
      .send({ firstName: 'A', lastName: 'B', email, password: 'sifre1234' });
    createdUserIds.push(registerRes.body.user.id);

    const res = await request(app).post('/auth/login').send({ email, password: 'yanlissifre' });

    expect(res.status).toBe(401);
  });

  it('olmayan email ile 401 döner', async () => {
    const res = await request(app)
      .post('/auth/login')
      .send({ email: uniqueEmail(), password: 'sifre1234' });

    expect(res.status).toBe(401);
  });
});

describe('POST /auth/forgot-password', () => {
  beforeEach(() => sendPasswordResetEmail.mockClear());

  it('kayıtlı email için 6 haneli kod üretir ve gönderir, 200 döner', async () => {
    const email = uniqueEmail();
    const registerRes = await request(app)
      .post('/auth/register')
      .send({ firstName: 'A', lastName: 'B', email, password: 'eskisifre1' });
    createdUserIds.push(registerRes.body.user.id);

    const res = await request(app).post('/auth/forgot-password').send({ email });

    expect(res.status).toBe(200);
    expect(sendPasswordResetEmail).toHaveBeenCalledWith(email, expect.stringMatching(/^\d{6}$/));
  });

  it('kayıtlı olmayan email için de aynı yanıtı döner ve mail göndermez (enumeration koruması)', async () => {
    const res = await request(app).post('/auth/forgot-password').send({ email: uniqueEmail() });

    expect(res.status).toBe(200);
    expect(sendPasswordResetEmail).not.toHaveBeenCalled();
  });

  it('geçersiz email ile 400 döner', async () => {
    const res = await request(app).post('/auth/forgot-password').send({ email: 'gecersiz-email' });

    expect(res.status).toBe(400);
  });
});

describe('POST /auth/reset-password', () => {
  beforeEach(() => sendPasswordResetEmail.mockClear());

  async function registerAndRequestCode() {
    const email = uniqueEmail();
    const registerRes = await request(app)
      .post('/auth/register')
      .send({ firstName: 'A', lastName: 'B', email, password: 'eskisifre1' });
    createdUserIds.push(registerRes.body.user.id);

    await request(app).post('/auth/forgot-password').send({ email });
    const [, code] = sendPasswordResetEmail.mock.calls.at(-1);
    return { email, code };
  }

  it('doğru kodla şifreyi günceller; eski şifre artık çalışmaz, yenisi çalışır', async () => {
    const { email, code } = await registerAndRequestCode();

    const res = await request(app)
      .post('/auth/reset-password')
      .send({ email, code, password: 'yenisifre1' });
    expect(res.status).toBe(200);

    const oldLogin = await request(app).post('/auth/login').send({ email, password: 'eskisifre1' });
    expect(oldLogin.status).toBe(401);

    const newLogin = await request(app).post('/auth/login').send({ email, password: 'yenisifre1' });
    expect(newLogin.status).toBe(200);
  });

  it('kod ikinci kez kullanılamaz', async () => {
    const { email, code } = await registerAndRequestCode();

    await request(app).post('/auth/reset-password').send({ email, code, password: 'yenisifre1' });
    const res = await request(app)
      .post('/auth/reset-password')
      .send({ email, code, password: 'baskasifre1' });

    expect(res.status).toBe(400);
  });

  it('yanlış kodla 400 döner', async () => {
    const { email } = await registerAndRequestCode();

    const res = await request(app)
      .post('/auth/reset-password')
      .send({ email, code: '000000', password: 'yenisifre1' });

    expect(res.status).toBe(400);
  });

  it('kısa yeni şifre ile 400 döner', async () => {
    const { email, code } = await registerAndRequestCode();

    const res = await request(app)
      .post('/auth/reset-password')
      .send({ email, code, password: '123' });

    expect(res.status).toBe(400);
  });
});
