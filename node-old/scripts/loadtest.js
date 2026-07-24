// Haritayı aynı anda açan çok sayıda kullanıcıyı simüle eder: districts/categories/places
// endpoint'lerine yüksek eşzamanlılıkla istek atar, sunucunun ayakta kalıp kalmadığını ve
// hata oranını raporlar. Gerçek çalışan sunucuya karşı çalışır — önce `npm run dev`/`npm start`.
const autocannon = require('autocannon');

const BASE_URL = process.env.LOAD_TEST_URL || 'http://localhost:3000';
const CONNECTIONS = Number(process.env.LOAD_TEST_CONNECTIONS) || 300;
const DURATION = Number(process.env.LOAD_TEST_DURATION) || 15;

const scenarios = [
  { name: 'GET /districts', path: '/districts' },
  { name: 'GET /categories', path: '/categories' },
  { name: 'GET /places', path: '/places' },
  { name: 'GET /places (filtreli)', path: '/places?districtId=1' },
];

async function runScenario({ name, path }) {
  console.log(`\n=== ${name} — ${CONNECTIONS} eşzamanlı bağlantı, ${DURATION}sn ===`);

  const result = await autocannon({
    url: `${BASE_URL}${path}`,
    connections: CONNECTIONS,
    duration: DURATION,
  });

  console.log(autocannon.printResult(result));
  return result;
}

async function main() {
  let hadFailure = false;

  for (const scenario of scenarios) {
    const result = await runScenario(scenario);
    const failed = result.errors > 0 || result.non2xx > 0 || result.timeouts > 0;

    if (failed) {
      hadFailure = true;
      console.error(
        `HATA — ${scenario.name}: ${result.errors} bağlantı hatası, ${result.non2xx} non-2xx yanıt, ${result.timeouts} timeout`
      );
    } else {
      console.log(`OK — ${scenario.name}: 0 hata, ${result['2xx']} başarılı yanıt`);
    }
  }

  if (hadFailure) {
    console.error('\nLoad test BAŞARISIZ: en az bir senaryoda hata/timeout oluştu.');
    process.exitCode = 1;
  } else {
    console.log('\nLoad test BAŞARILI: tüm senaryolarda 0 hata, sunucu ayakta kaldı.');
  }
}

main().catch((err) => {
  console.error('Load test çalıştırılamadı:', err);
  process.exitCode = 1;
});
