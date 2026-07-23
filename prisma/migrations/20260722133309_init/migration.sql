-- CreateTable
CREATE TABLE "ilceler" (
    "id" SERIAL NOT NULL,
    "ilce" TEXT NOT NULL,

    CONSTRAINT "ilceler_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "harita_filtre" (
    "id" SERIAL NOT NULL,
    "baslik" TEXT NOT NULL,
    "sira" INTEGER NOT NULL,

    CONSTRAINT "harita_filtre_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "koordinatlar" (
    "id" SERIAL NOT NULL,
    "ilce_id" INTEGER NOT NULL,
    "harita_filtre_id" INTEGER NOT NULL,
    "konum_adi" TEXT NOT NULL,
    "enlem" DOUBLE PRECISION NOT NULL,
    "boylam" DOUBLE PRECISION NOT NULL,
    "aciklama" TEXT,

    CONSTRAINT "koordinatlar_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "sikayet_talepler" (
    "id" SERIAL NOT NULL,
    "kind" TEXT NOT NULL,
    "description" TEXT NOT NULL,
    "koordinat_id" INTEGER,
    "enlem" DOUBLE PRECISION,
    "boylam" DOUBLE PRECISION,
    "olusturulma_tarihi" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "sikayet_talepler_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "yeni_yer_talepleri" (
    "id" SERIAL NOT NULL,
    "konum_adi" TEXT NOT NULL,
    "harita_filtre_id" INTEGER,
    "description" TEXT NOT NULL,
    "enlem" DOUBLE PRECISION NOT NULL,
    "boylam" DOUBLE PRECISION NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'pending',
    "olusturulma_tarihi" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "yeni_yer_talepleri_pkey" PRIMARY KEY ("id")
);

-- AddForeignKey
ALTER TABLE "koordinatlar" ADD CONSTRAINT "koordinatlar_ilce_id_fkey" FOREIGN KEY ("ilce_id") REFERENCES "ilceler"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "koordinatlar" ADD CONSTRAINT "koordinatlar_harita_filtre_id_fkey" FOREIGN KEY ("harita_filtre_id") REFERENCES "harita_filtre"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
