-- CreateTable
CREATE TABLE "admin_islem_kayitlari" (
    "id" SERIAL NOT NULL,
    "eylem" TEXT NOT NULL,
    "hedef_tip" TEXT NOT NULL,
    "hedef_id" INTEGER NOT NULL,
    "ip_adresi" TEXT,
    "detay" JSONB,
    "olusturulma_tarihi" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "admin_islem_kayitlari_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE INDEX "admin_islem_kayitlari_hedef_tip_hedef_id_idx" ON "admin_islem_kayitlari"("hedef_tip", "hedef_id");

-- CreateIndex
CREATE INDEX "admin_islem_kayitlari_olusturulma_tarihi_idx" ON "admin_islem_kayitlari"("olusturulma_tarihi");
