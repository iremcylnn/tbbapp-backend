const request = require('supertest');
const app = require('../app');
const prisma = require('../lib/prisma');

const ADMIN_KEY = process.env.ADMIN_API_KEY;
const createdIds = [];
const createdUserIds = [];
let token;

beforeAll(async () => {
  const email = `feedback-test-${Date.now()}@example.com`;
  const res = await request(app)
    .post('/auth/register')
    .send({ firstName: 'Test', lastName: 'Kullanıcı', email, password: 'sifre1234' });
  token = res.body.token;
  createdUserIds.push(res.body.user.id);
});

afterAll(async () => {
  if (createdIds.length) {
    await prisma.feedbackSubmission.deleteMany({ where: { id: { in: createdIds } } });
  }
  if (createdUserIds.length) {
    await prisma.user.deleteMany({ where: { id: { in: createdUserIds } } });
  }
  await prisma.$disconnect();
});

describe('POST /feedback', () => {
  it('giriş yapmadan 401 döner', async () => {
    const res = await request(app)
      .post('/feedback')
      .send({ kind: 'complaint', description: 'test şikayeti' });

    expect(res.status).toBe(401);
  });

  it('geçerli kind ile 201 döner, kaydı giriş yapan kullanıcı adına oluşturur', async () => {
    const res = await request(app)
      .post('/feedback')
      .set('Authorization', `Bearer ${token}`)
      .send({ kind: 'complaint', description: 'test şikayeti' });

    expect(res.status).toBe(201);
    expect(res.body.kind).toBe('complaint');
    createdIds.push(res.body.id);
  });

  it('geçersiz kind için 400 döner', async () => {
    const res = await request(app)
      .post('/feedback')
      .set('Authorization', `Bearer ${token}`)
      .send({ kind: 'invalid-kind', description: 'x' });

    expect(res.status).toBe(400);
  });

  it('description eksikse 400 döner', async () => {
    const res = await request(app)
      .post('/feedback')
      .set('Authorization', `Bearer ${token}`)
      .send({ kind: 'request' });

    expect(res.status).toBe(400);
  });

  it('description 2000 karakteri geçerse 400 döner', async () => {
    const res = await request(app)
      .post('/feedback')
      .set('Authorization', `Bearer ${token}`)
      .send({ kind: 'request', description: 'a'.repeat(2001) });

    expect(res.status).toBe(400);
  });
});

describe('GET /feedback (admin)', () => {
  it('admin key olmadan 401 döner', async () => {
    const res = await request(app).get('/feedback');
    expect(res.status).toBe(401);
  });

  it('yanlış admin key ile 401 döner', async () => {
    const res = await request(app).get('/feedback').set('x-admin-key', 'yanlis-key');
    expect(res.status).toBe(401);
  });

  it('doğru admin key ile listeyi ve gönderen kullanıcı bilgisini döner', async () => {
    const res = await request(app).get('/feedback').set('x-admin-key', ADMIN_KEY);

    expect(res.status).toBe(200);
    expect(Array.isArray(res.body)).toBe(true);
    const created = res.body.find((f) => createdIds.includes(f.id));
    expect(created.user).toEqual(
      expect.objectContaining({ firstName: 'Test', lastName: 'Kullanıcı' })
    );
  });
});
