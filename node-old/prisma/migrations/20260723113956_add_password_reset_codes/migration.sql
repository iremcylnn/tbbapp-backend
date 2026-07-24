-- CreateTable
CREATE TABLE "sifre_sifirlama_kodlari" (
    "id" SERIAL NOT NULL,
    "kullanici_id" INTEGER NOT NULL,
    "kod_hash" TEXT NOT NULL,
    "son_kullanma_tarihi" TIMESTAMP(3) NOT NULL,
    "kullanilma_tarihi" TIMESTAMP(3),
    "olusturulma_tarihi" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "sifre_sifirlama_kodlari_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE INDEX "sifre_sifirlama_kodlari_kullanici_id_idx" ON "sifre_sifirlama_kodlari"("kullanici_id");

-- AddForeignKey
ALTER TABLE "sifre_sifirlama_kodlari" ADD CONSTRAINT "sifre_sifirlama_kodlari_kullanici_id_fkey" FOREIGN KEY ("kullanici_id") REFERENCES "kullanicilar"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
