# Panduan CI/CD GitHub — Universal Card Central (UCC)

Repository: `https://github.com/hasanarofid/MLM-universalcardcentral.git`

Dokumen ini menjelaskan alur **Continuous Integration (CI)** dan **Continuous Deployment (CD)** menggunakan **GitHub Actions** agar setiap perubahan kode otomatis teruji sebelum masuk ke production.

---

## 1. Struktur Workflow

1. **CI Pipeline (`.github/workflows/ci.yml`)**
   - **Trigger:** Setiap `push` atau `pull_request` ke branch `main` atau `master`.
   - **Job 1 (`laravel-tests`):**
     - Menyiapkan PHP 8.3 & ekstensi yang dibutuhkan.
     - Menjalankan `composer install`, `php artisan key:generate`, lalu `php artisan test` (menggunakan SQLite `:memory:` dan seluruh migration otomatis).
   - **Job 2 (`frontend-build`):**
     - Menyiapkan Node.js 22.
     - Menjalankan `npm ci` dan `npm run build` untuk memastikan aset Vue 3 + Tailwind CSS ter-compile tanpa error.

2. **CD Pipeline (`.github/workflows/deploy.yml`)**
   - **Trigger:** Berjalan otomatis setelah workflow **CI - Laravel & Vue Build** berstatus `success` pada branch `main`/`master`, atau bisa dijalankan manual melalui tombol **Run workflow** (`workflow_dispatch`) di tab **Actions** GitHub.
   - **Langkah Deploy di Server:**
     1. `git pull origin <branch>`
     2. `composer install --no-dev --optimize-autoloader --no-interaction`
     3. `npm ci && npm run build`
     4. `php artisan migrate --force`
     5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`

---

## 2. Konfigurasi GitHub Secrets (Wajib untuk CD)

Buka repository GitHub -> **Settings** -> **Secrets and variables** -> **Actions** -> **New repository secret**, lalu tambahkan:

| Nama Secret | Keterangan | Contoh |
| :--- | :--- | :--- |
| `SSH_HOST` | IP Address atau domain server/VPS/Plesk | `103.xxx.xxx.xxx` |
| `SSH_USER` | Username SSH server | `deployer` atau `root` |
| `SSH_PRIVATE_KEY` | Isi private key SSH (`~/.ssh/id_ed25519`) | `-----BEGIN OPENSSH PRIVATE KEY----- ...` |
| `SSH_PORT` | Port SSH (opsional, default `22`) | `22` |
| `APP_DIR` | Path absolut folder project di server | `/var/www/vhosts/domain.com/httpdocs` |

> **Catatan:** Jika belum ingin mengaktifkan auto-deploy ke server, workflow `ci.yml` tetap berjalan normal untuk mengecek test & build di setiap push, dan Anda bisa men-deploy manual lewat tab Actions setelah secrets diisi.

---

## 3. Rekomendasi Branch Protection Rules

Agar branch utama aman dari kode yang error:
1. Buka **Settings** -> **Branches** -> **Add branch protection rule**.
2. Isi **Branch name pattern** dengan `master` (atau `main`).
3. Centang **Require a pull request before merging**.
4. Centang **Require status checks to pass before merging**, lalu pilih:
   - `PHPUnit & Migration Tests`
   - `Vite & Vue 3 Production Build`

---

## 4. Workflow Harian Developer

```bash
# 1. Pastikan remote mengarah ke repo UCC
git remote -v
# origin  https://github.com/hasanarofid/MLM-universalcardcentral.git (fetch)
# origin  https://github.com/hasanarofid/MLM-universalcardcentral.git (push)

# 2. Jalankan test lokal sebelum push
php artisan test

# 3. Commit & push
git add .
git commit -m "feat: deskripsi perubahan"
git push origin master
```

Setelah `git push`, pantau statusnya di tab **Actions** pada halaman GitHub repository Anda.

