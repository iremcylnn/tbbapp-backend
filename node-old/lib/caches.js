const TTLCache = require('./cache');

// Harita açılışında en çok vurulan 3 okuma ucu için paylaşımlı cache instance'ları.
// districts/categories neredeyse hiç değişmediği için uzun TTL; places admin onayıyla
// değişebildiği için kısa TTL + onay anında explicit invalidate (bkz. routes/newPlaceRequests.js).
const districtsCache = new TTLCache(10 * 60 * 1000);
const categoriesCache = new TTLCache(10 * 60 * 1000);
const placesCache = new TTLCache(60 * 1000);

module.exports = { districtsCache, categoriesCache, placesCache };
