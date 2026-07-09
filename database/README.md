# Database Documentation

Dokumentasi ini menjelaskan struktur database **Toko Kelontong** berdasarkan file
migration yang berada di direktori `database/migrations/`.

Project ini menggunakan **Laravel** dengan database relasional. Setiap tabel
bisnis dimiliki oleh seorang `user` (multi-tenant sederhana berbasis user).

---

## Daftar Tabel

| No | Tabel                | Sumber Migration                                          | Kategori        |
| -- | -------------------- | --------------------------------------------------------- | --------------- |
| 1  | `users`              | `0001_01_01_000000_create_users_table.php`                | Core / Auth     |
| 2  | `password_reset_tokens` | `0001_01_01_000000_create_users_table.php`             | Core / Auth     |
| 3  | `sessions`           | `0001_01_01_000000_create_users_table.php`                | Core / Auth     |
| 4  | `cache`              | `0001_01_01_000001_create_cache_table.php`                | System          |
| 5  | `cache_locks`        | `0001_01_01_000001_create_cache_table.php`                | System          |
| 6  | `jobs`               | `0001_01_01_000002_create_jobs_table.php`                 | System (Queue)  |
| 7  | `job_batches`        | `0001_01_01_000002_create_jobs_table.php`                 | System (Queue)  |
| 8  | `failed_jobs`        | `0001_01_01_000002_create_jobs_table.php`                 | System (Queue)  |
| 9  | `items`              | `2026_05_29_000001_create_items_table.php`                | Bisnis          |
| 10 | `cash_transactions`  | `2026_05_29_000002_create_cash_transactions_table.php`    | Bisnis          |
| 11 | `stock_movements`    | `2026_05_29_000003_create_stock_movements_table.php`      | Bisnis          |

Kolom `role` pada tabel `users` ditambahkan kemudian melalui
`2026_06_20_000004_add_role_to_users_table.php`.

---

## Entity Relationship Diagram (ERD)

```
+----------------+        +-------------------+        +---------------------+
|     users      |        |      items        |        |  stock_movements    |
+----------------+        +-------------------+        +---------------------+
| id (PK)        |<-------+ user_id (FK)      |<-------+ user_id (FK)        |
| name           |  1..N  | id (PK)           |  1..N  | item_id (FK)        |
| email (UNIQUE) |        | user_id (FK)      |        | id (PK)             |
| email_verified |        | sku               |        | type (enum)         |
| password       |        | name              |        | quantity            |
| role (enum)    |        | description       |        | stock_before        |
| remember_token |        | unit              |        | stock_after         |
| created_at     |        | purchase_price    |        | notes               |
| updated_at     |        | selling_price     |        | occurred_at         |
+----------------+        | current_stock     |        | created_at          |
        |                 | minimum_stock     |        | updated_at          |
        |                 | is_active         |        +---------------------+
        | 1..N            | created_at        |
        |                 | updated_at        |        +---------------------+
        |                 +-------------------+        | cash_transactions   |
        +-------------->+ user_id (FK)      |        +---------------------+
                          type (enum)               | id (PK)             |
                          amount                    | type (enum)         |
                          description               | amount              |
                          transaction_date          | description         |
                          created_at                | transaction_date    |
                          updated_at                | created_at          |
                                                   | updated_at          |
                                                   +---------------------+

Tabel Sistem (tanpa relasi bisnis langsung):

+------------------+        +-----------------+        +------------------+
|      cache       |        |   cache_locks   |        |      jobs        |
+------------------+        +-----------------+        +------------------+
| key (PK)         |        | key (PK)        |        | id (PK)          |
| value            |        | owner           |        | queue (idx)      |
| expiration (idx) |        | expiration (idx)|        | payload          |
+------------------+        +-----------------+        | attempts         |
                                                      | reserved_at      |
+------------------+        +-----------------+        | available_at     |
| job_batches      |        |  failed_jobs    |        | created_at       |
+------------------+        +-----------------+        +------------------+
| id (PK)          |        | id (PK)         |
| name             |        | uuid (UNIQUE)   |        +-----------------+
| total_jobs       |        | connection      |        | sessions        |
| pending_jobs     |        | queue           |        +-----------------+
| failed_jobs      |        | payload         |        | id (PK)         |
| failed_job_ids   |        | exception       |        | user_id (FK)    |
| options          |        | failed_at       |        | ip_address      |
| cancelled_at     |        +-----------------+        | user_agent      |
| created_at       |                                  | payload         |
| finished_at      |        +-----------------+        | last_activity   |
+------------------+        | password_reset  |        +-----------------+
                            | _tokens         |
                            +-----------------+
                            | email (PK)      |
                            | token           |
                            | created_at      |
                            +-----------------+
```

---

## Detail Tabel Bisnis

### 1. `users`

Tabel utama pengguna sistem. Berisi akun yang bisa login ke aplikasi.

| Kolom              | Tipe                | Keterangan                                       |
| ------------------ | ------------------- | ------------------------------------------------ |
| `id`               | `BIGINT UNSIGNED` PK| Primary key, auto increment                      |
| `name`             | `VARCHAR`           | Nama pengguna                                    |
| `email`            | `VARCHAR` (UNIQUE)  | Email untuk login                                |
| `email_verified_at`| `TIMESTAMP` NULL    | Waktu verifikasi email                           |
| `password`         | `VARCHAR`           | Password (hashed)                                |
| `role`             | `VARCHAR` (enum)    | Role user. Nilai dari `App\Enums\UserRole`:      |
|                    |                     | - `shop_owner`  (Shop Owner)                     |
|                    |                     | - `shop_keeper` (Shop Keeper)                    |
|                    |                     | - `customer`    (Customer, default)              |
| `remember_token`   | `VARCHAR` NULL      | Token "remember me"                              |
| `created_at`       | `TIMESTAMP`         | Waktu dibuat                                     |
| `updated_at`       | `TIMESTAMP`         | Waktu diperbarui                                 |

**Catatan:**
- Kolom `role` ditambahkan oleh migration
  `2026_06_20_000004_add_role_to_users_table.php`.
- Default value untuk `role` adalah `customer`.

---

### 2. `items`

Master data barang yang dijual di toko. Setiap barang dimiliki oleh satu
`user` (multi-tenant).

| Kolom            | Tipe                       | Keterangan                                      |
| ---------------- | -------------------------- | ----------------------------------------------- |
| `id`             | `BIGINT UNSIGNED` PK       | Primary key                                     |
| `user_id`        | `BIGINT UNSIGNED` FK       | Referensi ke `users.id` (cascade on delete)     |
| `sku`            | `VARCHAR` NULL             | Stock Keeping Unit, opsional                    |
| `name`           | `VARCHAR`                  | Nama barang                                     |
| `description`    | `TEXT` NULL                | Deskripsi barang                                |
| `unit`           | `VARCHAR` default `pcs`    | Satuan barang (pcs, kg, dll)                    |
| `purchase_price` | `DECIMAL(15,2)` default 0  | Harga beli                                      |
| `selling_price`  | `DECIMAL(15,2)` default 0  | Harga jual                                      |
| `current_stock`  | `DECIMAL(12,2)` default 0  | Stok saat ini                                   |
| `minimum_stock`  | `DECIMAL(12,2)` default 0  | Batas minimum stok (untuk notifikasi)           |
| `is_active`      | `BOOLEAN` default `true`   | Status aktif barang                             |
| `created_at`     | `TIMESTAMP`                | Waktu dibuat                                    |
| `updated_at`     | `TIMESTAMP`                | Waktu diperbarui                                |

**Index & Constraint:**
- `UNIQUE (user_id, sku)` – SKU unik per user.
- `INDEX (user_id, name)` – Pencarian barang per user.

**Relasi:**
- `user_id` → `users.id` (FK, `cascadeOnDelete`)

---

### 3. `cash_transactions`

Catatan transaksi kas (uang masuk dan uang keluar) per user.

| Kolom              | Tipe                       | Keterangan                                   |
| ------------------ | -------------------------- | -------------------------------------------- |
| `id`               | `BIGINT UNSIGNED` PK       | Primary key                                  |
| `user_id`          | `BIGINT UNSIGNED` FK       | Referensi ke `users.id` (cascade on delete)  |
| `type`             | `ENUM`                     | Jenis transaksi:                             |
|                    |                            | - `cash_in`  (uang masuk)                    |
|                    |                            | - `cash_out` (uang keluar)                   |
| `amount`           | `DECIMAL(15,2)`            | Nominal transaksi                            |
| `description`      | `VARCHAR` NULL             | Keterangan / catatan                         |
| `transaction_date` | `DATE`                     | Tanggal transaksi                            |
| `created_at`       | `TIMESTAMP`                | Waktu dibuat                                 |
| `updated_at`       | `TIMESTAMP`                | Waktu diperbarui                             |

**Index:**
- `INDEX (user_id, transaction_date)` – Riwayat kas per user berdasarkan tanggal.

**Relasi:**
- `user_id` → `users.id` (FK, `cascadeOnDelete`)

---

### 4. `stock_movements`

Riwayat pergerakan stok barang. Setiap perubahan stok (masuk, keluar, atau
penyesuaian) dicatat di tabel ini untuk audit trail.

| Kolom           | Tipe                       | Keterangan                                          |
| --------------- | -------------------------- | --------------------------------------------------- |
| `id`            | `BIGINT UNSIGNED` PK       | Primary key                                         |
| `user_id`       | `BIGINT UNSIGNED` FK       | Referensi ke `users.id` (cascade on delete)         |
| `item_id`       | `BIGINT UNSIGNED` FK       | Referensi ke `items.id` (cascade on delete)         |
| `type`          | `ENUM`                     | Jenis pergerakan:                                   |
|                 |                            | - `in`         (stok masuk)                          |
|                 |                            | - `out`        (stok keluar)                         |
|                 |                            | - `adjustment` (penyesuaian stok)                   |
| `quantity`      | `DECIMAL(12,2)`            | Jumlah perubahan                                    |
| `stock_before`  | `DECIMAL(12,2)`            | Stok sebelum perubahan                             |
| `stock_after`   | `DECIMAL(12,2)`            | Stok sesudah perubahan                              |
| `notes`         | `VARCHAR` NULL             | Catatan tambahan                                    |
| `occurred_at`   | `TIMESTAMP`                | Waktu kejadian pergerakan                           |
| `created_at`    | `TIMESTAMP`                | Waktu record dibuat                                 |
| `updated_at`    | `TIMESTAMP`                | Waktu record diperbarui                             |

**Index:**
- `INDEX (user_id, occurred_at)` – Pergerakan per user berdasarkan waktu.
- `INDEX (item_id, occurred_at)` – Pergerakan per barang berdasarkan waktu.

**Relasi:**
- `user_id` → `users.id` (FK, `cascadeOnDelete`)
- `item_id` → `items.id` (FK, `cascadeOnDelete`)

---

## Tabel Sistem

Tabel-tabel berikut adalah bawaan Laravel untuk kebutuhan internal aplikasi
(auth, cache, antrian job). Tidak terkait langsung dengan logika bisnis.

### `password_reset_tokens`
| Kolom        | Tipe          | Keterangan                  |
| ------------ | ------------- | --------------------------- |
| `email` (PK) | `VARCHAR`     | Email user                  |
| `token`      | `VARCHAR`     | Token reset password        |
| `created_at` | `TIMESTAMP`   | Waktu token dibuat          |

### `sessions`
| Kolom           | Tipe           | Keterangan                |
| --------------- | -------------- | ------------------------- |
| `id` (PK)       | `VARCHAR`      | Session ID                |
| `user_id` (idx) | `BIGINT` NULL  | FK ke `users.id`          |
| `ip_address`    | `VARCHAR(45)`  | IP address user           |
| `user_agent`    | `TEXT` NULL    | User agent browser        |
| `payload`       | `LONGTEXT`     | Data session              |
| `last_activity` (idx) | `INTEGER` | Timestamp last activity   |

### `cache`
| Kolom            | Tipe         | Keterangan              |
| ---------------- | ------------ | ----------------------- |
| `key` (PK)       | `VARCHAR`    | Cache key               |
| `value`          | `MEDIUMTEXT` | Cache value (serialized)|
| `expiration` (idx) | `BIGINT`   | Unix timestamp expired  |

### `cache_locks`
| Kolom              | Tipe        | Keterangan              |
| ------------------ | ----------- | ----------------------- |
| `key` (PK)         | `VARCHAR`   | Lock key                |
| `owner`            | `VARCHAR`   | Lock owner identifier   |
| `expiration` (idx) | `BIGINT`    | Unix timestamp expired  |

### `jobs`
| Kolom          | Tipe                  | Keterangan             |
| -------------- | --------------------- | ---------------------- |
| `id` (PK)      | `BIGINT UNSIGNED`     | Primary key            |
| `queue` (idx)  | `VARCHAR`             | Nama antrian           |
| `payload`      | `LONGTEXT`            | Data job (serialized)  |
| `attempts`     | `UNSIGNED SMALLINT`   | Jumlah percobaan       |
| `reserved_at`  | `UNSIGNED INT` NULL   | Waktu di-reserve       |
| `available_at` | `UNSIGNED INT`        | Waktu tersedia         |
| `created_at`   | `UNSIGNED INT`        | Waktu dibuat           |

### `job_batches`
| Kolom            | Tipe          | Keterangan                 |
| ---------------- | ------------- | -------------------------- |
| `id` (PK)        | `VARCHAR`     | Batch ID                   |
| `name`           | `VARCHAR`     | Nama batch                 |
| `total_jobs`     | `INTEGER`     | Total job dalam batch      |
| `pending_jobs`   | `INTEGER`     | Job yang masih menunggu    |
| `failed_jobs`    | `INTEGER`     | Job yang gagal             |
| `failed_job_ids` | `LONGTEXT`    | List ID job yang gagal     |
| `options`        | `MEDIUMTEXT`  | Opsi batch                 |
| `cancelled_at`   | `INTEGER`     | Waktu dibatalkan           |
| `created_at`     | `INTEGER`     | Waktu dibuat               |
| `finished_at`    | `INTEGER`     | Waktu selesai              |

### `failed_jobs`
| Kolom                            | Tipe            | Keterangan            |
| -------------------------------- | --------------- | --------------------- |
| `id` (PK)                        | `BIGINT UNSIGNED`| Primary key          |
| `uuid` (UNIQUE)                  | `VARCHAR`       | UUID job              |
| `connection`                     | `VARCHAR`       | Nama connection      |
| `queue`                          | `VARCHAR`       | Nama antrian         |
| `payload`                        | `LONGTEXT`      | Data job             |
| `exception`                      | `LONGTEXT`      | Pesan error          |
| `failed_at`                      | `TIMESTAMP`     | Waktu gagal          |
| `INDEX (connection, queue, failed_at)` | -          | Index untuk pencarian|

---

## Ringkasan Relasi

| Relasi                            | Tipe      | Keterangan                                    |
| --------------------------------- | --------- | --------------------------------------------- |
| `users` → `items`                 | 1 to N    | Satu user memiliki banyak barang              |
| `users` → `cash_transactions`     | 1 to N    | Satu user memiliki banyak transaksi kas       |
| `users` → `stock_movements`       | 1 to N    | Satu user memiliki banyak pergerakan stok     |
| `items` → `stock_movements`       | 1 to N    | Satu barang memiliki banyak pergerakan stok   |
| `users` → `sessions`              | 1 to N    | Satu user bisa punya banyak session           |

> **Catatan Multi-tenant:** Semua data bisnis (`items`, `cash_transactions`,
> `stock_movements`) di-scope per `user_id`. Artinya data seorang user
> terisolasi dari user lain, dan setiap user hanya bisa mengelola datanya
> sendiri. Penghapusan user akan menghapus seluruh data terkait secara
> otomatis (`cascadeOnDelete`).
