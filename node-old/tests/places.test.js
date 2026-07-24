const request = require('supertest');
const app = require('../app');
const prisma = require('../lib/prisma');

afterAll(() => prisma.$disconnect());

describe('GET /places', () => {
  it('düz (flat) place objeleri döner, nested district/category yok (map.md: MapPlace)', async () => {
    const res = await request(app).get('/places');

    expect(res.status).toBe(200);
    expect(res.body.length).toBeGreaterThan(0);

    const place = res.body[0];
    expect(place).toEqual(
      expect.objectContaining({
        id: expect.any(Number),
        districtId: expect.any(Number),
        categoryId: expect.any(Number),
        name: expect.any(String),
        latitude: expect.any(Number),
        longitude: expect.any(Number),
      })
    );
    expect(place.district).toBeUndefined();
    expect(place.category).toBeUndefined();
  });

  it('districtId filtresini uygular', async () => {
    const res = await request(app).get('/places?districtId=1');

    expect(res.status).toBe(200);
    expect(res.body.length).toBeGreaterThan(0);
    expect(res.body.every((p) => p.districtId === 1)).toBe(true);
  });

  it('geçersiz districtId için 400 döner', async () => {
    const res = await request(app).get('/places?districtId=abc');
    expect(res.status).toBe(400);
  });
});

describe('GET /places/:id', () => {
  it('var olan bir yeri döner', async () => {
    const res = await request(app).get('/places/1');

    expect(res.status).toBe(200);
    expect(res.body.id).toBe(1);
  });

  it('geçersiz id için 400 döner', async () => {
    const res = await request(app).get('/places/abc');
    expect(res.status).toBe(400);
  });

  it('olmayan id için 404 döner', async () => {
    const res = await request(app).get('/places/999999');
    expect(res.status).toBe(404);
  });
});
