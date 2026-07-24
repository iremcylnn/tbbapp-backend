-- CreateIndex
CREATE INDEX "sikayet_talepler_kind_idx" ON "sikayet_talepler"("kind");

-- CreateIndex
CREATE INDEX "sikayet_talepler_olusturulma_tarihi_idx" ON "sikayet_talepler"("olusturulma_tarihi");

-- CreateIndex
CREATE INDEX "yeni_yer_talepleri_status_idx" ON "yeni_yer_talepleri"("status");

-- CreateIndex
CREATE INDEX "yeni_yer_talepleri_olusturulma_tarihi_idx" ON "yeni_yer_talepleri"("olusturulma_tarihi");
