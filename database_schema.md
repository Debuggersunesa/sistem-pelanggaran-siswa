# DATABASE SCHEMA KNOWLEDGE BASE

Project: Sistem Informasi Pelanggaran Siswa (SISP)
Database Name: `db_sistem_pelanggaran`

**CRITICAL INSTRUCTION FOR AI AGENT:**
Whenever generating SQL queries, PHP backend logic, or UI forms, you MUST strictly adhere to this exact database schema. DO NOT invent, guess, or assume column names. If a column is not listed here, it does not exist.

## 1. Master Data Tables

### `tingkat`

- `id_tingkat` (INT, Primary Key, Auto Increment)
- `tingkat` (VARCHAR)

### `program_keahlian`

- `id_program_keahlian` (INT, Primary Key, Auto Increment)
- `program_keahlian` (VARCHAR)
- `deskripsi` (VARCHAR)

### `guru` (Used for Admin, Guru BK, and Guru Pelajaran Login)

- `kode_guru` (CHAR, Primary Key)
- `nama_pengguna` (VARCHAR)
- `role` (VARCHAR) -> MUST be exactly: 'admin', 'guru_bk', or 'guru_pelajaran'
- `username` (VARCHAR)
- `password` (VARCHAR, hashed)
- `aktif` (ENUM: 'Y','N')
- `jabatan` (VARCHAR)
- `telp` (VARCHAR)
- `foto` (VARCHAR, Nullable)

### `jenis_pelanggaran`

- `id_jenis_pelanggaran` (INT, Primary Key, Auto Increment)
- `jenis` (VARCHAR)
- `poin` (INT)

### `tahun_ajaran`

- `id_tahun_ajaran` (INT, Primary Key, Auto Increment)
- `tahun` (VARCHAR)
- `aktif` (ENUM: 'Y','N')

### `profil_sekolah`

- `id_profil_sekolah` (INT, Primary Key, Auto Increment)
- `nama_sekolah` (VARCHAR)
- `alamat_sekolah` (VARCHAR)
- `kota` (VARCHAR)

### `ortu_wali`

- `id_ortu_wali` (INT, Primary Key, Auto Increment)
- `nama` (VARCHAR)
- `status_hubungan` (ENUM: 'Ayah', 'Ibu', 'Wali')
- `pekerjaan` (VARCHAR)
- `no_telp` (VARCHAR)
- `alamat` (VARCHAR)

### `kelas`

- `id_kelas` (INT, Primary Key, Auto Increment)
- `id_tingkat` (INT, Foreign Key -> tingkat.id_tingkat)
- `id_program_keahlian` (INT, Foreign Key -> program_keahlian.id_program_keahlian)
- `rombel` (INT)
- `kode_guru` (CHAR, Foreign Key -> guru.kode_guru)

### `siswa` (Used for Siswa Login)

- `nis` (INT, Primary Key)
- `nama_siswa` (VARCHAR)
- `jenis_kelamin` (ENUM: 'Laki - Laki', 'Perempuan')
- `alamat` (VARCHAR)
- `foto` (VARCHAR, Nullable)
- `password` (VARCHAR, hashed)
- `status` (ENUM: 'aktif', 'tidak_aktif', 'lulus', 'pindah')
- `id_ortu_wali` (INT, Foreign Key -> ortu_wali.id_ortu_wali, Nullable)
- `id_kelas` (INT, Foreign Key -> kelas.id_kelas, Nullable)
- `id_tahun_ajaran` (INT, Foreign Key -> tahun_ajaran.id_tahun_ajaran, Nullable)

---

## 2. Transaction & Record Tables

### `pelanggaran_siswa`

- `id_pelanggaran_siswa` (INT, Primary Key, Auto Increment)
- `tanggal` (DATETIME)
- `nis` (INT, Foreign Key -> siswa.nis)
- `id_jenis_pelanggaran` (INT, Foreign Key -> jenis_pelanggaran.id_jenis_pelanggaran)
- `id_tahun_ajaran` (INT, Foreign Key -> tahun_ajaran.id_tahun_ajaran)
- `keterangan` (TEXT)

### `perjanjian_ortu`

- `id_perjanjian_ortu` (INT, Primary Key, Auto Increment)
- `tanggal` (DATETIME)
- `id_pelanggaran_siswa` (INT, Foreign Key -> pelanggaran_siswa.id_pelanggaran_siswa)
- `status` (ENUM: 'Masih Proses', 'Selesai')
- `foto_dokumen` (VARCHAR)
- `tingkat` (VARCHAR)

### `perjanjian_siswa`

- `id_perjanjian_siswa` (INT, Primary Key, Auto Increment)
- `tanggal` (DATETIME)
- `id_pelanggaran_siswa` (INT, Foreign Key -> pelanggaran_siswa.id_pelanggaran_siswa)
- `status` (ENUM: 'Masih Proses', 'Selesai')
- `foto_dokumen` (VARCHAR)
- `tingkat` (VARCHAR)

### `surat_pindah`

- `id_surat_pindah` (INT, Primary Key, Auto Increment)
- `sekolah_tujuan` (VARCHAR)
- `alasan_pindah` (TEXT)

### `surat_keluar`

- `id_surat_keluar` (INT, Primary Key, Auto Increment)
- `no_surat` (VARCHAR)
- `jenis_surat` (VARCHAR)
- `id_surat_pindah` (INT, Foreign Key -> surat_pindah.id_surat_pindah, Nullable)
- `nis` (INT, Foreign Key -> siswa.nis)
- `tanggal_pembuatan_surat` (DATE)
- `id_profil_sekolah` (INT, Foreign Key -> profil_sekolah.id_profil_sekolah)
- `id_tahun_ajaran` (INT, Foreign Key -> tahun_ajaran.id_tahun_ajaran)
- `tingkat` (VARCHAR)
