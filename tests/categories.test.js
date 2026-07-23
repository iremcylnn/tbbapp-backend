const request = require('supertest');
const app = require('../app');
const prisma = require('../lib/prisma');

afterAll(() => prisma.$disconnect());

describe('GET /categories', () => {
  it('kategorileri {id, title} şeklinde, "order" alanı olmadan döner (map.md: MapCategory)', async () => {
    const res = await request(app).get('/categories');

    expect(res.status).toBe(200);
    expect(res.body.length).toBeGreaterThan(0);
    for (const category of res.body) {
      expect(Object.keys(category).sort()).toEqual(['id', 'title']);
    }
  });
});
