/*
  Warnings:

  - Changed the type of `kind` on the `sikayet_talepler` table. No cast exists, the column would be dropped and recreated, which cannot be done if there is data, since the column is required.

*/
-- CreateEnum
CREATE TYPE "sikayet_talep_turu" AS ENUM ('complaint', 'request');

-- AlterTable
ALTER TABLE "sikayet_talepler" DROP COLUMN "kind",
ADD COLUMN     "kind" "sikayet_talep_turu" NOT NULL;
