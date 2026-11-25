# Desain mobile-first
Pakai TailAdmin, token terang/gelap, Blade dan Alpine. Bahasa Indonesia, format IDR dan tanggal Indonesia. Ponsel satu kolom dan daftar transaksi tanpa scroll horizontal; desktop pakai sidebar dan tabel/grid responsif.
- Navigasi bawah: Beranda, Transaksi, Dompet, Laporan, Lainnya. Lainnya berisi Kategori dan Pengaturan Rumah Tangga. Pengaturan Identitas hanya untuk sistem admin. Autentikasi dan onboarding berupa halaman penuh.
- Wireframe beranda: identitas → Catat Transaksi → saldo dan ringkasan bulan → transaksi terbaru → navigasi. Tanpa dompet, arahkan ke Tambah Dompet.
- Wireframe transaksi: tiga jenis → nominal dan tanggal → dompet dan kategori atau asal/tujuan/biaya → catatan → Simpan. Tampilkan total potongan transfer. Wireframe identitas: nama → logo dan favicon dengan pratinjau/pemulihan → Simpan.
- Pakai modal untuk formulir singkat: transaksi, dompet, kategori, kode, koreksi saldo awal dan identitas. Panel bawah di ponsel, dialog tengah di desktop. Safe area dan target sentuh 44×44 px. Isi bisa digulir dan Simpan terjangkau saat keyboard virtual terbuka.
- Fade backdrop dan pergeseran ringan sekitar 180 ms, hormati reduced motion. Kelola fokus awal, fokus terbatas dan pemulihan fokus. ESC/tutup eksplisit, konfirmasi perubahan belum disimpan, cegah submit ganda, simpan input saat gagal dan tampilkan error dekat field. Tutup setelah sukses dan perbarui data. Rancang keadaan kosong, loading, gagal dan sukses.
- Uji lebar 320px, 375px dan desktop, Safari iOS dan Chrome Android, Tab, Escape dan reduced motion.
