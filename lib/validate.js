// value verilmemişse undefined, geçersizse null, geçerliyse pozitif tamsayı döner.
function parsePositiveInt(value) {
  if (value === undefined || value === null || value === '') return undefined;
  const n = Number(value);
  return Number.isInteger(n) && n > 0 ? n : null;
}

function isFiniteNumber(value) {
  return typeof value === 'number' && Number.isFinite(value);
}

module.exports = { parsePositiveInt, isFiniteNumber };
