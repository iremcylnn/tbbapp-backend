/*
  Warnings:

  - Added the required column `kullanici_id` to the `sikayet_talepler` table without a default value. This is not possible if the table is not empty.
  - Added the required column `kullanici_id` to the `yeni_yer_talepleri` table without a default value. This is not possible if the table is not empty.

*/
-- AlterTable
ALTER TABLE "sikayet_talepler" ADD COLUMN     "kullanici_id" INTEGER NOT NULL;

-- AlterTable
ALTER TABLE "yeni_yer_talepleri" ADD COLUMN     "kullanici_id" INTEGER NOT NULL;

-- CreateTable
CREATE TABLE "kullanicilar" (
    "id" SERIAL NOT NULL,
    "ad" TEXT NOT NULL,
    "soyad" TEXT NOT NULL,
    "email" TEXT NOT NULL,
    "sifre" TEXT NOT NULL,
    "olusturulma_tarihi" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "kullanicilar_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "kullanicilar_email_key" ON "kullanicilar"("email");

-- AddForeignKey
ALTER TABLE "sikayet_talepler" ADD CONSTRAINT "sikayet_talepler_kullanici_id_fkey" FOREIGN KEY ("kullanici_id") REFERENCES "kullanicilar"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "yeni_yer_talepleri" ADD CONSTRAINT "yeni_yer_talepleri_kullanici_id_fkey" FOREIGN KEY ("kullanici_id") REFERENCES "kullanicilar"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
