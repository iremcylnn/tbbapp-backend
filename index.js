require('dotenv').config();

if (!process.env.DATABASE_URL) {
  console.error('HATA: DATABASE_URL ortam değişkeni tanımlı değil. .env dosyasını kontrol edin (bkz. .env.example).');
  process.exit(1);
}
if (!process.env.ADMIN_API_KEY) {
  console.error('HATA: ADMIN_API_KEY ortam değişkeni tanımlı değil. .env dosyasını kontrol edin (bkz. .env.example).');
  process.exit(1);
}

const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const morgan = require('morgan');

const prisma = require('./lib/prisma');
const districtsRouter = require('./routes/districts');
const categoriesRouter = require('./routes/categories');
const placesRouter = require('./routes/places');
const feedbackRouter = require('./routes/feedback');
const newPlaceRequestsRouter = require('./routes/newPlaceRequests');

const isProduction = process.env.NODE_ENV === 'production';

const app = express();
app.use(helmet());
app.use(cors());
app.use(express.json());
if (!isProduction) {
  app.use(morgan('dev'));
}

app.get('/', (req, res) => {
  res.json({ status: 'ok' });
});

app.use('/districts', districtsRouter);
app.use('/categories', categoriesRouter);
app.use('/places', placesRouter);
app.use('/feedback', feedbackRouter);
app.use('/new-place-requests', newPlaceRequestsRouter);

app.use((req, res) => {
  res.status(404).json({ error: 'Bulunamadı' });
});

// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  console.error(err);
  res.status(err.status || 500).json({
    error: isProduction ? 'Sunucu hatası' : err.message,
  });
});

const port = process.env.PORT || 3000;
const server = app.listen(port, () => {
  console.log(`Sunucu http://localhost:${port} adresinde çalışıyor`);
});

async function shutdown(signal) {
  console.log(`${signal} alındı, kapatılıyor...`);
  server.close();
  await prisma.$disconnect();
  process.exit(0);
}
process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
