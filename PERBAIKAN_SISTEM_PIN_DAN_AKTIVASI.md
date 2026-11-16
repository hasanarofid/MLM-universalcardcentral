# Laporan Perbaikan Sistem — Alur Daftar Free, PIN Aktivasi & Jaringan 2 Kaki

Dokumen ini merangkum seluruh perbaikan fitur yang telah diterapkan pada sistem **Universal Card Central (UCC)** sesuai masukan terbaru klien.

---

## 1. Pendaftaran Free (`/register`) — Mudah & Cepat

- **Form Ringkas:** Calon member cukup mengisi:
  - **Nama Lengkap**
  - **No. HP / WhatsApp**
  - **Email**
  - **Alamat Lengkap**
  - **Password** & **Kode Referral**
- **Status Awal (`Belum Aktif`):** Pendaftar baru langsung bisa masuk ke dashboard dengan status **ID Belum Aktif (Akun Free)**, sehingga **belum** memicu pencairan bonus rekrut maupun royalty cluster sebelum melakukan aktivasi PIN.

---

## 2. Sistem PIN Aktivasi (`Bronze` & `Silver`)

- **Tipe PIN Aktivasi:**
  - **PIN Bronze (`UCCB-XXXXXXXX`):** Nilai aktivasi **Rp 100.000**
  - **PIN Silver (`UCCS-XXXXXXXX`):** Nilai aktivasi **Rp 350.000** (langsung berhak atas Card Visa Belanja Silver)
- **2 Cara Aktivasi ID Member Free:**
  1. **Input Kode PIN di Dashboard:** Jika member sudah membeli PIN dari Admin / Stock Center / Sponsor, cukup masukkan kode PIN di panel aktivasi Dashboard → ID langsung **Aktif** dan bonus rekrut (10%) + royalty cluster (10%) otomatis berjalan.
  2. **Upload Bukti Transfer Pembelian PIN:** Member dapat memilih paket PIN (`Bronze` / `Silver`), mengunggah bukti transfer pembayaran, dan mengirimkannya ke Admin.

---

## 3. Verifikasi Order PIN & Distribusi Stok PIN (Admin & Recruiter)

- **Admin Induk / Stock Center (`/admin/voucher-wallet`):**
  - Admin menerima notifikasi otomatis saat ada member mengunggah bukti transfer pembelian PIN.
  - Admin mengecek bukti bayar lalu klik **Approve & Kirim PIN** → sistem otomatis membuat kode PIN unik (`UCCB-` / `UCCS-`) dan mengirimkannya langsung ke dashboard/dompet PIN member beserta notifikasi.
  - Admin juga dapat **Generate & Kirim PIN** langsung ke username member tertentu.
- **Sponsor / Recruiter Mengaktifkan Member dengan Stok PIN:**
  - Recruiter yang memiliki stok PIN dapat **mendaftarkan member baru** sekaligus mengaktifkannya menggunakan stok PIN, atau klik **Aktifkan dgn PIN** langsung pada daftar referral yang masih berstatus *Belum Aktif*.

---

## 4. Penempatan Jaringan MLM 2 Kaki (`Kiri` / `Kanan`)

- Saat aktivasi PIN berlangsung, member ditempatkan pada struktur jaringan **2 kaki (`Kiri` / `Kanan`)** di bawah sponsor/upline (mendukung pilihan manual kaki `Kiri`/`Kanan` oleh recruiter atau penempatan otomatis berimbang saat aktivasi mandiri).
- Penghitung jaringan (`left_count` / `right_count`) upline otomatis bertambah saat ID resmi aktif.

---

## 5. Kelengkapan Profil Setelah Aktivasi (`/profile`)

- Setelah ID aktif menggunakan PIN, member dapat melengkapi data **KTP (14 field standar)**, unggah foto KTP, data ahli waris, rekening bank pencairan, serta ubah password di menu **Profil** yang sudah terintegrasi di dalam panel.

---

## 6. Verifikasi Pengujian Sistem

Seluruh alur di atas telah diuji dengan automated test (`php artisan test`) dengan hasil **36 tests passed (135 assertions)**, meliputi:
- Daftar Free (`inactive`) tanpa pencairan bonus sebelum aktivasi PIN.
- Pengajuan beli PIN via upload bukti bayar → Approval Admin → Generate PIN ke dompet member.
- Aktivasi ID mandiri menggunakan kode PIN (`Silver`/`Bronze`) + pencairan bonus rekrut & royalty cluster.
- Pendaftaran & aktivasi member oleh Recruiter menggunakan stok PIN pada jaringan 2 kaki (`Kiri`/`Kanan`).
