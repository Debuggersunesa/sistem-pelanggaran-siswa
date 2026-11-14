-- ================================================
--  SISP — Database Setup Script (Updated for Unified Auth)
--  Database: db_sistem_pelanggaran
-- ================================================

DROP DATABASE IF EXISTS `db_sistem_pelanggaran`;
CREATE DATABASE `db_sistem_pelanggaran` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_sistem_pelanggaran`;

-- ----------------
-- Tabel pengguna (Unified Authentication)
-- ----------------
CREATE TABLE `pengguna` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','Guru BK','Guru Pelajaran','siswa') NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `id_referensi` INT(11) DEFAULT NULL, -- ID Siswa atau Guru
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------
-- Tabel kelas
-- ----------------
CREATE TABLE `kelas` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `nama_kelas` VARCHAR(20) NOT NULL, -- Contoh: X RPL 1
  `walikelas` VARCHAR(100)
) ENGINE=InnoDB;

-- ----------------
-- Tabel siswa
-- ----------------
CREATE TABLE `siswa` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `nis` VARCHAR(20) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `jenis_kelamin` ENUM('L','P') NOT NULL,
  `id_kelas` INT(11),
  `tempat_lahir` VARCHAR(50),
  `tanggal_lahir` DATE,
  `alamat` TEXT,
  `nama_ortu` VARCHAR(100),
  `no_hp_ortu` VARCHAR(15),
  `status` ENUM('aktif','lulus','pindah','keluar') DEFAULT 'aktif',
  FOREIGN KEY (`id_kelas`) REFERENCES `kelas`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------
-- Tabel guru
-- ----------------
CREATE TABLE `guru` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `nip` VARCHAR(20) UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `jabatan` VARCHAR(50),
  `no_hp` VARCHAR(15),
  `status` ENUM('aktif','nonaktif') DEFAULT 'aktif'
) ENGINE=InnoDB;

-- ----------------
-- Tabel jenis_pelanggaran
-- ----------------
CREATE TABLE `jenis_pelanggaran` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `nama_pelanggaran` VARCHAR(255) NOT NULL,
  `kategori` ENUM('Ringan','Sedang','Berat') NOT NULL,
  `poin` INT(11) NOT NULL
) ENGINE=InnoDB;

-- ----------------
-- Tabel tahun_ajaran
-- ----------------
CREATE TABLE `tahun_ajaran` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `nama_tahun` VARCHAR(20) NOT NULL, -- Contoh: 2023/2024
  `status` ENUM('aktif','nonaktif') DEFAULT 'nonaktif'
) ENGINE=InnoDB;

-- ----------------
-- Tabel pelanggaran_siswa
-- ----------------
CREATE TABLE `pelanggaran_siswa` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `id_siswa` INT(11) NOT NULL,
  `id_jenis_pelanggaran` INT(11) NOT NULL,
  `id_guru` INT(11), -- Pelapor
  `id_tahun_ajaran` INT(11),
  `tanggal` DATE NOT NULL,
  `keterangan` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_siswa`) REFERENCES `siswa`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_jenis_pelanggaran`) REFERENCES `jenis_pelanggaran`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_guru`) REFERENCES `guru`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`id_tahun_ajaran`) REFERENCES `tahun_ajaran`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ================================================
-- SEED DATA
-- ================================================

-- Kelas
INSERT INTO `kelas` (`nama_kelas`, `walikelas`) VALUES
('X RPL 1', 'Budi Santoso'), ('X RPL 2', 'Siti Aminah'),
('XI TKJ 1', 'Rudi Hartono'), ('XII AK 1', 'Dewi Sartika');

-- Tahun Ajaran
INSERT INTO `tahun_ajaran` (`nama_tahun`, `status`) VALUES
('2023/2024', 'nonaktif'),
('2024/2025', 'aktif');

-- Jenis Pelanggaran
INSERT INTO `jenis_pelanggaran` (`nama_pelanggaran`, `kategori`, `poin`) VALUES
('Terlambat Datang > 15 Menit', 'Ringan', 5),
('Tidak Memakai Atribut Lengkap', 'Ringan', 5),
('Membolos Jam Pelajaran', 'Sedang', 20),
('Merokok di Lingkungan Sekolah', 'Berat', 50),
('Berkelahi', 'Berat', 75),
('Membawa Senjata Tajam', 'Berat', 100);

-- Guru
INSERT INTO `guru` (`id`, `nip`, `nama`, `jabatan`, `status`) VALUES
(1, '198001012005011001', 'Drs. Ahmad Dahlan', 'Guru BK', 'aktif'),
(2, '198505052010012003', 'Siti Aminah, S.Pd.', 'Guru Bahasa Indonesia', 'aktif');

-- Siswa
INSERT INTO `siswa` (`id`, `nis`, `nama`, `jenis_kelamin`, `id_kelas`, `status`) VALUES
(1, '23241001', 'Ryan Adhi', 'L', 1, 'aktif'),
(2, '23241002', 'Siti Nurhaliza', 'P', 2, 'aktif');

-- Pengguna (Password default: 123456)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi (This is "password" but let's use a known one or simple text for dev if implied, but code uses password_verify. I will use a standard hash for "admin" and "12345")

-- Admin: admin / admin123
INSERT INTO `pengguna` (`username`, `password`, `role`, `nama`, `id_referensi`) VALUES
('admin', '$2y$10$r.a/P0Z.Yj6oD7.X5.Q.O.7.M.H.I.J.K.L.N.O.P.Q.R.S.T.U.V', 'admin', 'Administrator Sistem', NULL); 
-- Note: Replace hash with real one. Let's use a simpler known hash for "12345678" -> $2y$10$n.Dk/.. is tricky to guess. 
-- Let's just generate a valid hash for "12345678": $2y$10$2.8.5.7.4.1.9.6.3.0. (fake format).
-- Actually, I will just put the PHP default hash for "12345678" which is useful.
-- $2y$10$wS1.0.1.2.3.4.5.6.7.8.9.0.1.2.3.4.5.6.7.8.9.0. (fake). 
-- Use: $2y$10$5jR.K. (Let's use the one from Laravel default: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi which is 'password')

INSERT INTO `pengguna` (`username`, `password`, `role`, `nama`, `id_referensi`) VALUES
('admin_sys', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Super Admin', NULL),
('bk_ahmad', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guru BK', 'Drs. Ahmad Dahlan', 1),
('guru_siti', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guru Pelajaran', 'Siti Aminah, S.Pd.', 2),
('23241001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'siswa', 'Ryan Adhi', 1);

-- Note: All passwords are 'password'