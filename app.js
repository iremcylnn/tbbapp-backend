require('dotenv').config();

const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const morgan = require('morgan');
const compression = require('compression');

const prisma = require('./lib/prisma');
const authRouter = require('./routes/auth');
const districtsRouter = require('./routes/districts');
const categoriesRouter = require('./routes/categories');
const placesRouter = require('./routes/places');
const feedbackRouter = require('./routes/feedback');
const newPlaceRequestsRouter = require('./routes/newPlaceRequests');

const isProduction = process.env.NODE_ENV === 'production';
const isTest = process.env.NODE_ENV === 'test';

const app = express();
app.use(helmet());
app.use(compression());
app.use(cors());
app.use(express.json());
if (!isProduction && !isTest) {
  app.use(morgan('dev'));
}

// DB'ye gerçekten ping atar — DB down olduğunda da "ok" dönen sahte bir sağlık
// kontrolü olmasın diye (bir önceki sürümün bilinen eksiğiydi).
app.get('/', async (req, res) => {
  try {
    await prisma.$queryRaw`SELECT 1`;
    res.json({ status: 'ok' });
  } catch (err) {
    console.error('Sağlık kontrolü: DB bağlantısı kurulamadı', err);
    res.status(503).json({ status: 'degraded', error: 'DB bağlantısı kurulamadı' });
  }
});

app.use('/auth', authRouter);
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

module.exports = app;
