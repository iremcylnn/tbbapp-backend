// value verilmemişse undefined, geçersizse null, geçerliyse pozitif tamsayı döner.
function parsePositiveInt(value) {
  if (value === undefined || value === null || value === '') return undefined;
  const n = Number(value);
  return Number.isInteger(n) && n > 0 ? n : null;
}

function isFiniteNumber(value) {
  return typeof value === 'number' && Number.isFinite(value);
}

// Boş olmayan, belirtilen üst sınırı aşmayan bir string mi? (junk/abuse verisine karşı üst sınır)
function isNonEmptyString(value, maxLength = 2000) {
  return typeof value === 'string' && value.trim().length > 0 && value.trim().length <= maxLength;
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
function isValidEmail(value) {
  return typeof value === 'string' && EMAIL_RE.test(value.trim()) && value.trim().length <= 254;
}

module.exports = { parsePositiveInt, isFiniteNumber, isNonEmptyString, isValidEmail };
