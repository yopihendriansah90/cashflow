# Struktur tabel MySQL
Gunakan BIGINT UNSIGNED untuk identitas/FK, DATETIME UTC untuk waktu audit, DATE untuk tanggal transaksi, BIGINT untuk Rupiah. Kolom wajib kecuali bertanda opsional. Tambahkan timestamps pada entitas yang dapat berubah. Gunakan FK RESTRICT untuk data historis.
| Tabel | Kolom, relasi dan batasan |
| --- | --- |
| users | Skema Laravel yang ada; is_system_admin BOOLEAN DEFAULT FALSE (tahap identitas). |
| households | id PK, name VARCHAR(120). |
| household_memberships | id PK, household_id FK, user_id FK, role VARCHAR(10) owner/partner, ended_at opsional. Gunakan kolom generated nullable yang bernilai user_id dan role hanya saat aktif, dengan UNIQUE(active_user_id) dan UNIQUE(household_id, active_role). Indeks histori (household_id,user_id). |
| household_join_codes | id PK, household_id FK, created_by_membership_id FK, code_hash CHAR(64) UNIQUE, expires_at, used_at opsional, revoked_at opsional. Indeks (household_id, revoked_at, used_at). |
| wallets | id PK, household_id FK, name VARCHAR(120), type VARCHAR(16), brand_key VARCHAR(32) opsional, opened_on DATE, opening_balance BIGINT, archived_at opsional, holder_membership_id FK opsional. Indeks (household_id, opened_on). |
| wallet_opening_balance_revisions | id PK, wallet_id FK, actor_membership_id FK, before_amount dan after_amount BIGINT, created_at. Indeks (wallet_id,created_at). |
| categories | id PK, household_id FK opsional (NULL global), name VARCHAR(120), type VARCHAR(12), code VARCHAR(64) opsional UNIQUE untuk bawaan, archived_at opsional. Indeks (household_id,type,archived_at). |
| transactions | id PK, household_id FK, type VARCHAR(12), occurred_on DATE, amount BIGINT positif, fee_amount BIGINT nonnegatif default 0, source_wallet_id dan destination_wallet_id FK opsional sesuai jenis, category_id FK opsional hanya untuk transfer, note VARCHAR(500) opsional, creator_membership_id FK, deleted_at opsional. Indeks (household_id,occurred_on,deleted_at), indeks dompet asal dan tujuan dengan tanggal. |
| transaction_revisions | id PK, transaction_id FK, actor_membership_id FK, action VARCHAR(12), before_snapshot JSON opsional, after_snapshot JSON opsional, created_at. Indeks (transaction_id,created_at). |
| system_settings | id PK dengan satu baris id 1, app_name VARCHAR(120), logo_path dan favicon_path VARCHAR(255) opsional. |
Validasi ruang setiap referensi dan kategori sesuai jenis. Transfer wajib punya dua dompet berbeda, tanpa kategori langsung. Pemasukan hanya dompet tujuan, pengeluaran hanya dompet asal. Jenis selain transfer memakai biaya 0. Simpan kode stabil zakat, infak, sedekah dan biaya admin. Pertahankan baris keanggotaan setelah akses berakhir.
