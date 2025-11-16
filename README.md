# 🏟️ GoMabar: Platform Sparing dan Booking Olahraga

GoMabar adalah *platform* daring modern yang dirancang untuk memfasilitasi **pemesanan lapangan olahraga** dan **pencarian teman untuk sesi *main bareng*** (Mabar). Aplikasi ini berfokus pada pengalaman pengguna yang efisien dalam menemukan ketersediaan slot waktu dan mengelola jadwal pertandingan.

## 💡 Fitur Utama

| Fitur | Deskripsi | Status |
| :--- | :--- | :--- |
| **Booking Multi-Step** | Alur *form wizard* (3 langkah) untuk membuat sesi Mabar baru, mengintegrasikan Lapangan, Tanggal, dan Slot. | ✅ Selesai |
| **Manajemen Lapangan** | Admin CRUD Lapangan, termasuk harga *Weekday/Weekend*, *update* detail, dan *photo upload*. | ✅ Selesai |
| **Manajemen Waktu** | Pengaturan Jam Operasional (`FieldOperatingHours`) dan sistem **Blokir Jadwal** khusus (*Maintenance*). | ✅ Selesai |
| **Admin Panel (Dasar)** | Tabel data untuk Pengguna, Lapangan, dan Kategori dengan *server-side* pagination, sorting, dan filtering. | ✅ Selesai |
| **Aksi Dinamis** | Tombol *join* sesi Mabar yang *intelligent* (memeriksa *role*, ketersediaan slot, dan status pembayaran). | ✅ Selesai |
| **Otentikasi Role-Based** | Pembatasan akses Admin Panel menggunakan *middleware* dan *role-check* berbasis `userRole` di *client-side*. | ✅ Selesai |

---

## 🏗️ Arsitektur Teknologi

Proyek ini dibangun menggunakan pola *Headless* (Frontend terpisah dari Backend) untuk memastikan kecepatan, skalabilitas, dan pemisahan tugas yang jelas.

| Komponen | Teknologi Utama | Keterangan |
| :--- | :--- | :--- |
| **Frontend** | **Next.js** (App Router), TypeScript, Tailwind CSS | Menggunakan `next/font` (Rubik) dan `shadcn/ui`. |
| **State & Data** | **SWR** (Stale-While-Revalidate) & **React Hook Form** | Digunakan untuk *caching* data *live* dari API dan manajemen *state* formulir yang kompleks (dengan Zod). |
| **Animasi** | **Framer Motion** | Digunakan untuk transisi dan animasi yang menarik (*styling* Gen Z). |
| **Backend API** | **Laravel 10/11** | Menyediakan RESTful API, mengelola *logic* transaksi, dan *slot availability*. |
| **Autentikasi** | **Laravel Sanctum (SPA)** | Mengamankan API menggunakan *session*/*cookie* yang aman untuk *frontend* Next.js. |

---

## 🛠️ Panduan Instalasi Lokal (Dev Setup)

Untuk menjalankan proyek ini, Anda harus menjalankan *backend* dan *frontend* secara bersamaan.

### Prerequisites

* Node.js (v18+)
* PHP (v8.1+) & Composer
* Database MySQL/PostgreSQL

### Langkah 1: Setup Backend (Laravel API)

Instruksi ini dijalankan di *root directory* repositori **Backend** Anda (`lapangan-api`).

1.  **Instal Dependensi & Kunci Aplikasi:**
    ```bash
    composer install
    php artisan key:generate
    ```
2.  **Konfigurasi Lingkungan (.env):**
    Atur URL untuk mengatasi blokir keamanan Next.js dan pastikan otentikasi berfungsi:
    ```env
    APP_URL=[http://127.0.0.1:8000](http://127.0.0.1:8000)
    FRONTEND_URL=[http://127.0.0.1:3000](http://127.0.0.1:3000)
    SANCTUM_STATEFUL_DOMAINS=127.0.0.1:3000
    ```
3.  **Database:** (Ini akan menghapus semua data, pastikan Anda siap)
    ```bash
    php artisan migrate:fresh --seed
    ```
4.  **Buat Symlink Storage (Penting untuk Gambar):**
    ```bash
    php artisan storage:link
    ```
5.  **Jalankan Server Backend:** `php artisan serve`

### Langkah 2: Setup Frontend (Next.js)

Instruksi ini dijalankan di *root directory* repositori **Frontend** Anda (`lapangan-web`).

1.  **Instal Dependensi:**
    ```bash
    npm install
    # atau
    yarn
    ```
2.  **Konfigurasi API (.env.local):**
    ```env
    NEXT_PUBLIC_BACKEND_URL=[http://127.0.0.1:8000](http://127.0.0.1:8000)
    ```
3.  **Jalankan Server Frontend:**
    ```bash
    npm run dev
    ```
    *(Aplikasi akan berjalan di http://127.0.0.1:3000)*
