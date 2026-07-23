/*
  Warnings:

  - The `status` column on the `yeni_yer_talepleri` table would be dropped and recreated. This will lead to data loss if there is data in the column.

*/
-- CreateEnum
CREATE TYPE "yeni_yer_talep_durumu" AS ENUM ('pending', 'approved', 'rejected');

-- AlterTable
ALTER TABLE "yeni_yer_talepleri" DROP COLUMN "status",
ADD COLUMN     "status" "yeni_yer_talep_durumu" NOT NULL DEFAULT 'pending';
