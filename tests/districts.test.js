const request = require('supertest');
const app = require('../app');
const prisma = require('../lib/prisma');

afterAll(() => prisma.$disconnect());

describe('GET /districts', () => {
  it('ilçeleri {id, name} şeklinde döner', async () => {
    const res = await request(app).get('/districts');

    expect(res.status).toBe(200);
    expect(Array.isArray(res.body)).toBe(true);
    expect(res.body.length).toBeGreaterThan(0);
    expect(res.body[0]).toEqual(
      expect.objectContaining({ id: expect.any(Number), name: expect.any(String) })
    );
  });
});
