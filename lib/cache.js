// Basit in-memory TTL cache. Tek Node process'i içinde çalışır (birden fazla
// instance'a ölçeklenirse paylaşımlı bir store — ör. Redis — gerekir).
class TTLCache {
  constructor(ttlMs) {
    this.ttlMs = ttlMs;
    this.value = undefined;
    this.expiresAt = 0;
    this.pending = null;
  }

  // loader eş zamanlı birden çok istekte de sadece bir kez çalışır (cache stampede önleme).
  async get(loader) {
    const now = Date.now();
    if (this.value !== undefined && now < this.expiresAt) {
      return this.value;
    }
    if (!this.pending) {
      this.pending = loader()
        .then((value) => {
          this.value = value;
          this.expiresAt = Date.now() + this.ttlMs;
          return value;
        })
        .finally(() => {
          this.pending = null;
        });
    }
    return this.pending;
  }

  invalidate() {
    this.value = undefined;
    this.expiresAt = 0;
  }
}

module.exports = TTLCache;
