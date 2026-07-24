const request = require('supertest');
const app = require('../app');
const prisma = require('../lib/prisma');

const ADMIN_KEY = process.env.ADMIN_API_KEY;
const createdSubmissionIds = [];
const createdPlaceIds = [];
const createdUserIds = [];
let token;

beforeAll(async () => {
  const email = `newplace-test-${Date.now()}@example.com`;
  const res = await request(app)
    .post('/auth/register')
    .send({ firstName: 'Test', lastName: 'Kullanıcı', email, password: 'sifre1234' });
  token = res.body.token;
  createdUserIds.push(res.body.user.id);
});

afterAll(async () => {
  if (createdPlaceIds.length) {
    await prisma.place.deleteMany({ where: { id: { in: createdPlaceIds } } });
  }
  if (createdSubmissionIds.length) {
    await prisma.adminActionLog.deleteMany({
      where: { targetType: 'NewPlaceSubmission', targetId: { in: createdSubmissionIds } },
    });
    await prisma.newPlaceSubmission.deleteMany({ where: { id: { in: createdSubmissionIds } } });
  }
  if (createdUserIds.length) {
    await prisma.user.deleteMany({ where: { id: { in: createdUserIds } } });
  }
  await prisma.$disconnect();
});

async function createSubmission(overrides = {}) {
  const res = await request(app)
    .post('/new-place-requests')
    .set('Authorization', `Bearer ${token}`)
    .send({
      name: 'Test Yeri',
      description: 'test açıklaması',
      latitude: 40.98,
      longitude: 27.51,
      ...overrides,
    });
  createdSubmissionIds.push(res.body.id);
  return res;
}

describe('POST /new-place-requests', () => {
  it('giriş yapmadan 401 döner', async () => {
    const res = await request(app)
      .post('/new-place-requests')
      .send({ name: 'x', description: 'y', latitude: 40.9, longitude: 27.5 });

    expect(res.status).toBe(401);
  });

  it('geçerli veriyle 201 ve pending durumda kayıt oluşturur', async () => {
    const res = await createSubmission();

    expect(res.status).toBe(201);
    expect(res.body.status).toBe('pending');
  });

  it('name 200 karakteri geçerse 400 döner', async () => {
    const res = await request(app)
      .post('/new-place-requests')
      .set('Authorization', `Bearer ${token}`)
      .send({ name: 'a'.repeat(201), description: 'y', latitude: 40.9, longitude: 27.5 });

    expect(res.status).toBe(400);
  });

  it('latitude/longitude eksikse 400 döner', async () => {
    const res = await request(app)
      .post('/new-place-requests')
      .set('Authorization', `Bearer ${token}`)
      .send({ name: 'x', description: 'y' });

    expect(res.status).toBe(400);
  });
});

describe('GET /new-place-requests/mine', () => {
  it('giriş yapmadan 401 döner', async () => {
    const res = await request(app).get('/new-place-requests/mine');
    expect(res.status).toBe(401);
  });

  it('sadece giriş yapan kullanıcının kendi önerilerini döner', async () => {
    const own = await createSubmission({ name: 'Benim Önerim' });

    const otherEmail = `newplace-other-${Date.now()}@example.com`;
    const otherRes = await request(app)
      .post('/auth/register')
      .send({ firstName: 'Diğer', lastName: 'Kullanıcı', email: otherEmail, password: 'sifre1234' });
    createdUserIds.push(otherRes.body.user.id);

    const otherSubmission = await request(app)
      .post('/new-place-requests')
      .set('Authorization', `Bearer ${otherRes.body.token}`)
      .send({ name: 'Başkasının Önerisi', description: 'test açıklaması', latitude: 40.98, longitude: 27.51 });
    createdSubmissionIds.push(otherSubmission.body.id);

    const res = await request(app).get('/new-place-requests/mine').set('Authorization', `Bearer ${token}`);

    expect(res.status).toBe(200);
    expect(Array.isArray(res.body)).toBe(true);
    expect(res.body.some((r) => r.id === own.body.id)).toBe(true);
    expect(res.body.every((r) => r.id !== otherSubmission.body.id)).toBe(true);
  });
});

describe('GET /new-place-requests (admin)', () => {
  it('admin key olmadan 401 döner', async () => {
    const res = await request(app).get('/new-place-requests');
    expect(res.status).toBe(401);
  });

  it('doğru admin key ile listeyi ve gönderen kullanıcı bilgisini döner', async () => {
    const created = await createSubmission({ name: 'Admin Liste Testi' });

    const res = await request(app).get('/new-place-requests').set('x-admin-key', ADMIN_KEY);

    expect(res.status).toBe(200);
    expect(Array.isArray(res.body)).toBe(true);
    const found = res.body.find((r) => r.id === created.body.id);
    expect(found.user).toEqual(expect.objectContaining({ firstName: 'Test', lastName: 'Kullanıcı' }));
  });
});

describe('PATCH /new-place-requests/:id', () => {
  it('admin key olmadan 401 döner', async () => {
    const created = await createSubmission();

    const res = await request(app)
      .patch(`/new-place-requests/${created.body.id}`)
      .send({ status: 'rejected' });

    expect(res.status).toBe(401);
  });

  it('reddetme akışı: status rejected olur ve bir audit log kaydı oluşur', async () => {
    const created = await createSubmission({ name: 'Reddedilecek' });

    const res = await request(app)
      .patch(`/new-place-requests/${created.body.id}`)
      .set('x-admin-key', ADMIN_KEY)
      .send({ status: 'rejected' });

    expect(res.status).toBe(200);
    expect(res.body.status).toBe('rejected');

    const logs = await request(app)
      .get('/admin-actions')
      .query({ targetType: 'NewPlaceSubmission', targetId: created.body.id })
      .set('x-admin-key', ADMIN_KEY);
    expect(logs.body).toEqual([
      expect.objectContaining({ action: 'new_place_request.rejected', targetId: created.body.id }),
    ]);
  });

  it('onaylama akışı: gerçek bir Place oluşturur ve bir audit log kaydı bırakır', async () => {
    const created = await createSubmission({ name: 'Onaylanacak Yer', categoryId: 1 });

    const res = await request(app)
      .patch(`/new-place-requests/${created.body.id}`)
      .set('x-admin-key', ADMIN_KEY)
      .send({ status: 'approved', districtId: 1 });

    expect(res.status).toBe(200);
    expect(res.body.submission.status).toBe('approved');
    expect(res.body.place).toEqual(
      expect.objectContaining({ name: 'Onaylanacak Yer', districtId: 1, categoryId: 1 })
    );
    createdPlaceIds.push(res.body.place.id);

    // /places cache'i onay anında invalidate edilmeli — yeni yer hemen görünmeli.
    const placesRes = await request(app).get('/places');
    expect(placesRes.body.some((p) => p.id === res.body.place.id)).toBe(true);

    const logs = await request(app)
      .get('/admin-actions')
      .query({ targetType: 'NewPlaceSubmission', targetId: created.body.id })
      .set('x-admin-key', ADMIN_KEY);
    expect(logs.body).toEqual([
      expect.objectContaining({
        action: 'new_place_request.approved',
        targetId: created.body.id,
        metadata: { placeId: res.body.place.id, districtId: 1, categoryId: 1 },
      }),
    ]);
  });

  it('districtId olmadan onay 400 döner', async () => {
    const created = await createSubmission();

    const res = await request(app)
      .patch(`/new-place-requests/${created.body.id}`)
      .set('x-admin-key', ADMIN_KEY)
      .send({ status: 'approved' });

    expect(res.status).toBe(400);
  });

  it('geçersiz districtId için 400 döner', async () => {
    const created = await createSubmission({ categoryId: 1 });

    const res = await request(app)
      .patch(`/new-place-requests/${created.body.id}`)
      .set('x-admin-key', ADMIN_KEY)
      .send({ status: 'approved', districtId: 999999 });

    expect(res.status).toBe(400);
  });

  it('zaten karara bağlanmış öneriye tekrar dokununca 409 döner', async () => {
    const created = await createSubmission();
    await request(app)
      .patch(`/new-place-requests/${created.body.id}`)
      .set('x-admin-key', ADMIN_KEY)
      .send({ status: 'rejected' });

    const res = await request(app)
      .patch(`/new-place-requests/${created.body.id}`)
      .set('x-admin-key', ADMIN_KEY)
      .send({ status: 'approved', districtId: 1 });

    expect(res.status).toBe(409);
  });

  it('eşzamanlı iki onay isteğinde sadece biri başarılı olur, çift Place oluşmaz', async () => {
    const created = await createSubmission({ name: 'Yarış Testi', categoryId: 1 });

    const [res1, res2] = await Promise.all([
      request(app)
        .patch(`/new-place-requests/${created.body.id}`)
        .set('x-admin-key', ADMIN_KEY)
        .send({ status: 'approved', districtId: 1 }),
      request(app)
        .patch(`/new-place-requests/${created.body.id}`)
        .set('x-admin-key', ADMIN_KEY)
        .send({ status: 'approved', districtId: 1 }),
    ]);

    const statuses = [res1.status, res2.status].sort();
    expect(statuses).toEqual([200, 409]);

    const successRes = res1.status === 200 ? res1 : res2;
    createdPlaceIds.push(successRes.body.place.id);

    const places = await prisma.place.findMany({ where: { name: 'Yarış Testi' } });
    expect(places).toHaveLength(1);

    // Yarış kaybeden istek 409 ile döndü ve hiç transaction'a girmedi — audit log da tek satır.
    const logs = await prisma.adminActionLog.findMany({
      where: { targetType: 'NewPlaceSubmission', targetId: created.body.id },
    });
    expect(logs).toHaveLength(1);
  });
});

describe('GET /admin-actions', () => {
  it('admin key olmadan 401 döner', async () => {
    const res = await request(app).get('/admin-actions');
    expect(res.status).toBe(401);
  });

  it('geçersiz targetId ile 400 döner', async () => {
    const res = await request(app)
      .get('/admin-actions')
      .query({ targetId: 'abc' })
      .set('x-admin-key', ADMIN_KEY);
    expect(res.status).toBe(400);
  });
});
