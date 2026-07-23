const request = require('supertest');
const app = require('../app');
const prisma = require('../lib/prisma');

afterAll(() => prisma.$disconnect());

describe('GET /', () => {
  it('DB erişilebilirken 200 { status: "ok" } döner', async () => {
    const res = await request(app).get('/');

    expect(res.status).toBe(200);
    expect(res.body).toEqual({ status: 'ok' });
  });
});
