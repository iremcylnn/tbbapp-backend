const request = require('supertest');
const app = require('../app');
const prisma = require('../lib/prisma');

const createdUserIds = [];

afterAll(async () => {
  if (createdUserIds.length) {
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
