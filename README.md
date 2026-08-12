SiagaPasok

Pasokan Lokal Siap Sebelum Pengadaan.

SiagaPasok adalah Pre-Procurement Supply Orchestration System untuk membantu koordinasi rantai pasok lokal dalam konteks Program Makan Bergizi Gratis (MBG). Sistem ini menghubungkan kebutuhan SPPG dengan kesiapan pasokan KDKMP/Koperasi, memantau risiko pasokan, mendeteksi shortfall, mengaktifkan mekanisme fallback antar-KDKMP, dan mengevaluasi kesiapan hingga sistem menghasilkan status READY FOR PROCUREMENT secara otomatis.

Repository ini berisi Working MVP SiagaPasok yang dibangun sebagai aplikasi web internal berbasis Laravel + Inertia.js + React + PostgreSQL.

[!IMPORTANT]SiagaPasok bukan marketplace, sistem Purchase Order, sistem pembayaran, aplikasi pembiayaan, atau sistem pengadaan resmi. Batas operasional utama aplikasi berhenti pada status Ready for Procurement. Proses pengadaan resmi tetap berlangsung di luar SiagaPasok.

Daftar Isi

Tentang SiagaPasok

Masalah yang Ingin Diselesaikan

Cara Kerja SiagaPasok

Aktor dan Hak Akses

Fitur Utama

Prinsip Bisnis Utama

Arsitektur dan Tech Stack

Struktur Repository

Persyaratan Sistem

Clone dan Menjalankan Project di Local

Menjalankan SiagaPasok

Demo Mode

Akun Demo

Skenario Demo

Menjalankan Test

PostgreSQL Concurrency Test

Production-like Frontend Build

Update Repository Local

Troubleshooting

Batas Scope MVP

Foundation Documents

Catatan Keamanan dan Data Demo

Tentang SiagaPasok

SiagaPasok dirancang sebagai lapisan orkestrasi sebelum pengadaan.

Kebutuhan institusional seperti MBG dapat membutuhkan volume pasokan yang besar dan berulang, sedangkan pasokan lokal dapat berasal dari banyak produsen dengan volume, waktu panen, serta tingkat risiko yang berbeda-beda.

SiagaPasok membantu membuat kondisi tersebut terlihat sebagai satu operational picture yang dapat digunakan bersama oleh SPPG dan KDKMP.

Tujuan utamanya adalah:

membuat forecast kebutuhan SPPG terlihat lebih awal;

mengagregasi kesiapan pasokan lokal melalui KDKMP;

memastikan hanya komitmen pasokan yang valid yang dihitung sebagai Safe Supply;

mendeteksi kekurangan pasokan secara otomatis;

menyediakan mekanisme fallback antar-KDKMP;

menjaga keputusan penting melalui pola maker-checker;

menentukan Ready for Procurement dari kondisi aktual, bukan melalui tombol manual;

mempertahankan audit trail dan isolasi data antarorganisasi.

Masalah yang Ingin Diselesaikan

Masalah inti yang ditangani SiagaPasok adalah ketidaksesuaian antara:

Institutional Demand
        ↓
volume besar dan membutuhkan kepastian

dengan:

Fragmented Local Supply
        ↓
banyak produsen
volume berbeda
jadwal panen berbeda
risiko berbeda

Tanpa koordinasi yang baik, kekurangan pasokan dapat baru terlihat ketika waktu pengadaan sudah terlalu dekat.

SiagaPasok mencoba memindahkan visibilitas masalah tersebut ke tahap pra-pengadaan, sehingga organisasi terkait mempunyai waktu lebih panjang untuk melakukan recovery.

Cara Kerja SiagaPasok

Alur utama SiagaPasok:

FORECAST
    ↓
EXPECTED HARVEST
    ↓
COMMIT
    ↓
MONITOR
    ↓
SAFE SUPPLY
    ↓
SHORTFALL
    ↓
FALLBACK
    ↓
READINESS
    ↓
READY FOR PROCUREMENT
    ↓
FULFILMENT FEEDBACK

Secara ringkas:

SPPG membuat Forecast kebutuhan.

KDKMP mencatat Expected Harvest dari produsen internal.

KDKMP Operator membuat Supply Commitment.

KDKMP Manager melakukan approval.

Sistem menghitung Safe Supply, At-Risk Supply, Coverage, dan Shortfall.

Jika supply berisiko, confidence dapat turun dan Safe Supply ikut berubah.

Jika Shortfall muncul, KDKMP requester dapat membuat Fallback Request.

KDKMP lain dapat membuat source-backed Fallback Offer.

Kapasitas Offer di-reserve secara transaksional.

Manager requester dapat menerima seluruh atau sebagian Offer.

Sistem menghitung ulang Safe Supply.

Setiap contributor menyelesaikan Logistics Readiness dan Document Readiness.

Jika seluruh gate terpenuhi, sistem menghasilkan:

READY FOR PROCUREMENT

Proses procurement resmi dilakukan di luar SiagaPasok.

Sesudah proses resmi selesai, SPPG dapat mencatat Fulfilment Feedback sebagai learning loop.

Aktor dan Hak Akses

SiagaPasok menggunakan closed account model.

Tidak tersedia public registration.

Satu business user terikat ke satu organisasi.

System Admin

Bertanggung jawab pada area administratif:

organisasi;

akun pengguna;

role;

aktivasi/deaktivasi akun;

master data;

supply network configuration;

metadata audit administratif.

System Admin tidak memiliki operational override terhadap supply, commitment, fallback, readiness, atau Ready for Procurement.

SPPG User

Bertanggung jawab pada sisi kebutuhan:

membuat Forecast;

publish/revisi Forecast;

melihat Safe Supply secara agregat;

melihat Coverage dan Shortfall;

melihat contributor organization;

melihat readiness agregat;

melihat status Ready for Procurement;

mencatat Fulfilment Feedback.

SPPG tidak memperoleh akses ke detail produsen internal KDKMP.

KDKMP Operator / FRPL

Bertindak sebagai maker:

mengelola Producer Registry;

mencatat Expected Harvest;

membuat dan submit Supply Commitment;

memperbarui confidence sesuai rule;

membuat recovery request;

menyiapkan Fallback Request;

menyiapkan Fallback Offer;

menyiapkan Logistics Readiness;

menyiapkan Document Readiness.

Operator tidak boleh menyetujui record yang dibuatnya sendiri.

KDKMP Manager

Bertindak sebagai checker/decision maker:

approve/reject Supply Commitment;

approve/reject confidence recovery;

approve Fallback Request broadcast;

approve outgoing Fallback Offer;

Accept/Reject incoming Fallback Offer;

approve Logistics Readiness;

approve Document Readiness.

Fitur Utama

1. Closed Authentication & Role-Based Access

tidak ada public registration;

role-based landing page;

active account validation;

organization-scoped access;

cross-organization authorization.

2. Organization & Supply Network

SPPG;

KDKMP;

relasi PRIMARY;

relasi NETWORK.

PRIMARY digunakan untuk direct/base supply terhadap Forecast.

NETWORK digunakan sebagai kapasitas jaringan untuk recovery melalui Fallback.

3. Demand Forecast

SPPG dapat:

membuat Forecast;

menyimpan DRAFT;

publish;

revise;

cancel sesuai guardrail;

close setelah periode selesai.

4. Producer Registry

Data produsen merupakan data internal KDKMP.

Data tersebut tidak diekspos ke KDKMP lain maupun SPPG.

5. Expected Harvest

Expected Harvest berfungsi sebagai konteks perencanaan internal.

Expected Harvest tidak otomatis menjadi Safe Supply.

6. Supply Commitment & Maker-Checker

KDKMP Operator membuat range volume commitment.

Manager melakukan approval.

Approved payload tidak diedit langsung. Perubahan dilakukan melalui revision/version baru.

7. Confidence Monitoring

Confidence supply:

GREEN  = Aman
YELLOW = Berisiko
RED    = Kritis / unavailable

Semantik utamanya:

GREEN dapat masuk Safe Supply jika seluruh rule lain valid;

YELLOW keluar dari Safe Supply dan masuk At-Risk Supply;

RED tidak memberikan kontribusi supply.

8. Derived Supply Metrics

SiagaPasok menghitung secara backend:

Demand Target;

Direct Safe Supply;

Fallback Safe Supply;

Total Safe Supply;

At-Risk Supply;

Coverage;

Shortfall;

Surplus;

Volume Ready;

contributor organizations.

Nilai tersebut bukan field yang dapat diedit secara manual.

9. Fallback Recovery

Jika Shortfall muncul:

Fallback Request
    ↓
Manager Requester Approval
    ↓
Network Broadcast
    ↓
Source-backed Offer
    ↓
Supplier Manager Approval
    ↓
Atomic Capacity Reservation
    ↓
Requester Manager Accept / Reject

Fallback hanya masuk Safe Supply setelah Offer diterima dan source supply tetap valid.

10. Logistics & Document Readiness

Setiap effective contributor harus menyelesaikan:

Operator prepare
    ↓
Submit
    ↓
Manager review
    ↓
Approve / Reject

Readiness menggunakan versioning agar approval lama tidak diam-diam berlaku setelah data berubah.

11. Derived Ready for Procurement

READY FOR PROCUREMENT bukan toggle.

Status ini merupakan hasil evaluasi dari:

Volume Ready
AND
seluruh contributor Logistics Ready
AND
seluruh contributor Document Ready

Jika kondisi supply atau readiness memburuk, status dapat kembali menjadi Belum Siap secara otomatis.

12. Notifications & Audit Trail

Perubahan penting direkam untuk menjaga:

actor;

organization;

before/after state;

action;

reason;

traceability.

13. Fulfilment Feedback

Setelah proses resmi berlangsung di luar SiagaPasok, SPPG dapat mencatat hasil fulfilment sebagai feedback historis.

Fitur ini bukan sistem receiving, QC fisik, atau pembayaran.

14. Controlled Demo Utilities

Dalam Demo Mode tersedia utility untuk:

mengganti seeded demo account;

menjalankan skenario gangguan supply;

menjalankan fallback recovery;

menjalankan contributor readiness;

mengembalikan scenario ke baseline melalui Reset Demo.

Demo utility tetap menggunakan domain workflow yang sah dan tidak memaksa nilai Safe Supply/RFP secara manual.

Prinsip Bisnis Utama

Beberapa invariant penting yang dijaga aplikasi:

Expected Harvest ≠ Safe Supply

YELLOW ≠ Safe Supply

Offer AVAILABLE ≠ Fallback Safe Supply

Fallback Safe Supply
= accepted volume
+ source tetap valid

Ready for Procurement
= derived state

Bukan:

manual checkbox / toggle

Selain itu:

maker tidak boleh menjadi checker untuk record yang sama;

KDKMP A tidak boleh membaca data internal KDKMP B;

Fallback Offer harus memiliki source;

source capacity tidak boleh di-reserve dua kali;

critical multi-row mutation menggunakan database transaction/locking;

System Admin tidak mempunyai supply/RFP override.

Arsitektur dan Tech Stack

SiagaPasok dibangun sebagai Laravel modular monolith.

Backend

PHP ^8.3

Laravel ^13.8

Inertia Laravel ~3.0

PHPUnit ^12.5

Frontend

React ^19.2

Inertia React ^3.6

Vite ^8

Tailwind CSS ^4.3

shadcn

Base UI

Lucide React

Inter Variable melalui package lokal

Database

PostgreSQL

transaction untuk mutation kritis;

row locking untuk concurrency-sensitive workflow;

SQLite in-memory hanya digunakan pada regular automated tests tertentu.

Arsitektur Layer

Browser
   ↓
React / Inertia
   ↓
Laravel Routes
   ↓
Controllers / Form Requests / Policies
   ↓
Domain & Application Services
   ↓
Eloquent Models / Query Layer
   ↓
PostgreSQL

Frontend bukan source of truth untuk calculation atau business state.

Struktur Repository

Struktur penting repository:

SiagaPasok/
├── app/
│   ├── Enums/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   ├── Policies/
│   ├── Services/
│   │   ├── Audit/
│   │   ├── Commitment/
│   │   ├── Demo/
│   │   ├── Fallback/
│   │   ├── Forecast/
│   │   ├── Notification/
│   │   ├── Readiness/
│   │   └── Supply/
│   └── Support/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docs/
├── public/
├── resources/
│   ├── css/
│   └── js/
│       ├── components/
│       ├── Layouts/
│       └── Pages/
├── routes/
├── storage/
├── tests/
│   ├── Feature/
│   ├── Support/
│   └── Unit/
├── .env.example
├── composer.json
├── package.json
└── vite.config.js

Persyaratan Sistem

Sebelum menjalankan project, pastikan komputer sudah memiliki:

Wajib

Git

Digunakan untuk clone repository.

Cek:

git --version

PHP 8.3 atau lebih baru

Project mensyaratkan:

PHP ^8.3

Cek:

php -v

Pastikan PHP CLI yang aktif adalah versi yang benar.

Composer

Cek:

composer --version

Node.js

Frontend dependency saat ini membutuhkan Node.js:

^20.19.0
atau
>=22.12.0

Cek:

node -v
npm -v

PostgreSQL

PostgreSQL harus terpasang dan server harus berjalan.

Cek:

psql --version

Project tidak mengunci satu versi server PostgreSQL tertentu, tetapi gunakan release PostgreSQL yang masih didukung.

PHP PostgreSQL Driver

Pastikan extension berikut aktif:

pdo_pgsql
pgsql

Cek daftar extension:

php -m

Untuk automated tests reguler, pdo_sqlite juga perlu tersedia karena PHPUnit menggunakan SQLite in-memory pada banyak test.

Disarankan

Windows 10/11, Linux, atau macOS;

RAM minimum yang memadai untuk Laravel + Vite + PostgreSQL;

browser modern;

VS Code atau editor lain;

pgAdmin jika tidak ingin menggunakan psql.

Clone dan Menjalankan Project di Local

Bagian ini menjelaskan setup dari komputer yang belum memiliki source SiagaPasok.

1. Clone Repository

HTTPS:

git clone https://github.com/MosesSimangunsong/SiagaPasok.git

Masuk ke directory project:

cd SiagaPasok

Pastikan branch:

git branch --show-current

Repository utama saat ini menggunakan branch:

master

Jika diperlukan:

git checkout master

2. Buat File Environment

Project menyediakan .env.example.

Windows PowerShell

Copy-Item .env.example .env

Linux / macOS

cp .env.example .env

Jangan commit file .env.

3. Install Dependency PHP

Jalankan:

composer install

Composer akan menggunakan composer.lock sehingga dependency yang terpasang mengikuti versi project.

Jika ingin memeriksa apakah PHP dan extension yang diperlukan tersedia:

composer check-platform-reqs

4. Install Dependency Frontend

Karena repository memiliki package-lock.json, gunakan:

npm ci

npm ci lebih cocok untuk fresh clone karena mengikuti lockfile secara reproducible.

Jika node_modules sebelumnya sudah ada dan Anda sedang melakukan development biasa, npm install juga dapat digunakan bila memang diperlukan.

5. Buat Database PostgreSQL

Default database local SiagaPasok:

db_siagapasok

Cara A — menggunakan psql

Masuk ke PostgreSQL:

psql -U postgres

Buat database:

CREATE DATABASE db_siagapasok;

Keluar:

\q

Alternatif satu command:

createdb -U postgres db_siagapasok

Cara B — menggunakan pgAdmin

Buka pgAdmin.

Hubungkan ke PostgreSQL server.

Klik kanan Databases.

Pilih Create → Database.

Isi nama:

db_siagapasok

Simpan.

[!WARNING]Jangan menggunakan database yang berisi data penting untuk eksperimen destructive seperti migrate:fresh.

6. Konfigurasi .env

Buka .env lalu sesuaikan minimal bagian berikut:

APP_NAME=SiagaPasok
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=db_siagapasok
DB_USERNAME=postgres
DB_PASSWORD=YOUR_POSTGRES_PASSWORD

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

SIAGAPASOK_DEMO_MODE=true

Ganti:

YOUR_POSTGRES_PASSWORD

dengan password PostgreSQL local Anda.

Tentang SIAGAPASOK_DEMO_MODE

Untuk fresh clone yang ingin langsung mencoba keseluruhan aplikasi, disarankan:

SIAGAPASOK_DEMO_MODE=true

Karena SiagaPasok menggunakan closed account dan tidak menyediakan public registration, Demo Mode akan menyediakan deterministic demo organizations, users, producers, dan scenario awal.

Untuk environment non-demo:

SIAGAPASOK_DEMO_MODE=false

Demo utility akan dinonaktifkan.

7. Generate Application Key

Setelah dependency Composer terpasang:

php artisan key:generate

.env akan mendapatkan:

APP_KEY=base64:...

8. Bersihkan Cache Konfigurasi

Terutama setelah mengubah .env:

php artisan optimize:clear

Ini penting jika sebelumnya Laravel pernah menyimpan configuration cache.

9. Jalankan Migration

Pastikan PostgreSQL sudah hidup dan credential .env benar.

Kemudian:

php artisan migrate

Perintah ini membuat schema database yang dibutuhkan aplikasi.

Untuk melihat status migration:

php artisan migrate:status

[!CAUTION]Jangan menjalankan:

php artisan migrate:fresh

pada database utama yang memiliki data yang ingin dipertahankan. migrate:fresh menghapus seluruh table sebelum membuatnya kembali.

10. Jalankan Seeder

Untuk fresh local setup:

php artisan db:seed

Jika:

SIAGAPASOK_DEMO_MODE=true

maka DatabaseSeeder akan membuat:

reference/master data;

SPPG Badung Demo;

KDKMP Tani Sejahtera;

KDKMP Mitra Lestari;

demo users;

supply network;

18 simulated producers;

readiness requirement demo;

deterministic Forecast Kangkung;

baseline Supply Commitment.

Anda juga dapat menjalankan migration dan seed pada fresh database dengan:

php artisan migrate --seed

Namun langkah terpisah migrate lalu db:seed lebih mudah untuk troubleshooting.

11. Verifikasi Database Connection

Jalankan:

php artisan migrate:status

Jika daftar migration tampil tanpa database connection error, Laravel sudah dapat berkomunikasi dengan PostgreSQL.

Menjalankan SiagaPasok

Ada dua cara utama.

Opsi A — Menjalankan Service Secara Terpisah

Cara ini paling mudah untuk troubleshooting.

Terminal 1 — Laravel Server

php artisan serve

Default:

http://127.0.0.1:8000

Terminal 2 — Vite Development Server

npm run dev

Biarkan terminal ini tetap berjalan selama frontend development.

Terminal 3 — Queue Worker

Karena local configuration menggunakan database queue:

php artisan queue:work

Biarkan worker tetap berjalan jika ingin seluruh queued job diproses.

Kemudian buka:

http://127.0.0.1:8000/login

Opsi B — Satu Command Development

Repository menyediakan Composer development script:

composer run dev

Script tersebut menjalankan secara bersamaan:

Laravel development server;

queue listener;

Laravel Pail;

Vite development server.

Jika environment/terminal Anda memiliki masalah dengan proses concurrent, gunakan Opsi A.

Demo Mode

Demo Mode adalah environment khusus untuk demonstrasi dan evaluasi.

Aktifkan melalui:

SIAGAPASOK_DEMO_MODE=true

Setelah mengubah nilai tersebut:

php artisan optimize:clear
php artisan db:seed

Demo Mode menyediakan label:

SIMULASI

agar data demonstrasi tidak disalahartikan sebagai deployment atau data operasional nyata.

[!WARNING]Demo Mode dan akun demo tidak boleh digunakan sebagai konfigurasi production.

Akun Demo

Seluruh akun berikut dibuat oleh deterministic demo seeder.

Password seluruh akun:

SiagaPasokDemo2026!

Role

Organization

Email

System Admin

Platform

demo.admin@siagapasok.local

SPPG User

SPPG Badung Demo

sppg.badung@siagapasok.local

KDKMP Operator

KDKMP Tani Sejahtera

tani.operator@siagapasok.local

KDKMP Manager

KDKMP Tani Sejahtera

tani.manager@siagapasok.local

KDKMP Operator

KDKMP Mitra Lestari

mitra.operator@siagapasok.local

KDKMP Manager

KDKMP Mitra Lestari

mitra.manager@siagapasok.local

Login:

http://127.0.0.1:8000/login

[!CAUTION]Credential tersebut merupakan credential publik untuk controlled demo. Jangan menggunakan password yang sama pada environment nyata.

Skenario Demo

Demo deterministic menggunakan Forecast:

DEMO-FRC-KANGKUNG-400

Target:

400 kg

Baseline:

Demand                400 kg
PRIMARY Safe Supply   400 kg
Shortfall               0 kg

Supply utama dibentuk dari:

250 kg GREEN
+
150 kg GREEN
=
400 kg Direct Safe Supply

Controlled Disruption

Skenario dapat menurunkan commitment 150 kg:

GREEN
  ↓
YELLOW

Hasil:

Direct Safe Supply    250 kg
At-Risk Supply        150 kg
Shortfall             150 kg
Volume Ready          FALSE

Fallback Recovery

Requester membuat:

Fallback Request = 150 kg

NETWORK contributor menyediakan:

Source Capacity = 160 kg

Lalu:

Offer     = 160 kg
Reserve   = 160 kg
Accept    = 150 kg
Allocate  = 150 kg
Release   =  10 kg

Setelah acceptance:

Direct Safe Supply    250 kg
Fallback Safe Supply  150 kg
Total Safe Supply     400 kg
Shortfall               0 kg
Volume Ready           TRUE

Multi-Contributor Readiness

Kedua contributor harus menyelesaikan:

Logistics Readiness
+
Document Readiness

dengan:

Operator prepare/submit
        ↓
Manager approve

Setelah seluruh gate terpenuhi:

READY FOR PROCUREMENT = TRUE

Status tersebut dihasilkan secara derived oleh backend.

Tidak ada tombol yang dapat memaksa RFP menjadi TRUE secara manual.

Reset Demo

Pada account SPPG Badung Demo tersedia Reset Demo.

Reset akan mengembalikan controlled scenario ke baseline:

Demand       400 kg
Safe Supply  400 kg
Shortfall      0 kg
RFP          FALSE

Reset Demo dirancang hanya untuk deterministic demo data dan bukan pengganti reset database global.

Menjalankan Test

Regular Test Suite

Jalankan:

php artisan test

Atau melalui Composer:

composer test

Regular PHPUnit configuration menggunakan:

DB_CONNECTION=sqlite
DB_DATABASE=:memory:

untuk mayoritas Unit/Feature test.

Karena itu regular test suite cepat dan terisolasi dari database PostgreSQL utama.

[!IMPORTANT]Lulus pada SQLite tidak dianggap cukup untuk membuktikan concurrency PostgreSQL. Repository menyediakan gate terpisah untuk race-condition-sensitive workflow.

PostgreSQL Concurrency Test

Workflow Fallback dan Readiness memiliki mutation yang membutuhkan transaksi dan locking nyata.

Untuk itu repository menyediakan real PostgreSQL concurrency tests.

1. Buat Database Test Terpisah

Gunakan:

db_siagapasok_concurrency_test

Contoh:

createdb -U postgres db_siagapasok_concurrency_test

atau melalui pgAdmin.

[!DANGER]Jangan arahkan concurrency test ke:

db_siagapasok

Test ini dapat menjalankan destructive database rehearsal seperti migrate:fresh.

2. Aktifkan Gate — Windows PowerShell

$env:SIAGAPASOK_REAL_DB_CONCURRENCY="true"
$env:SIAGAPASOK_CONCURRENCY_DB_DATABASE="db_siagapasok_concurrency_test"

3. Aktifkan Gate — Linux/macOS

export SIAGAPASOK_REAL_DB_CONCURRENCY=true
export SIAGAPASOK_CONCURRENCY_DB_DATABASE=db_siagapasok_concurrency_test

4. Jalankan Fallback Concurrency Test

php artisan test tests/Feature/Fallback/FallbackRealDatabaseConcurrencyTest.php

5. Jalankan Readiness Concurrency Test

php artisan test tests/Feature/Readiness/ReadinessRealDatabaseConcurrencyTest.php

6. Jalankan PostgreSQL Demo Rehearsal

php artisan test tests/Feature/Demo/DemoPostgresRehearsalTest.php

Rehearsal memvalidasi lifecycle:

clean migration
    ↓
demo seed
    ↓
full controlled scenario
    ↓
Ready for Procurement
    ↓
Demo Reset
    ↓
baseline
    ↓
reseed

7. Matikan Environment Flag Setelah Selesai

Windows PowerShell

Remove-Item Env:SIAGAPASOK_REAL_DB_CONCURRENCY -ErrorAction SilentlyContinue
Remove-Item Env:SIAGAPASOK_CONCURRENCY_DB_DATABASE -ErrorAction SilentlyContinue

Linux/macOS

unset SIAGAPASOK_REAL_DB_CONCURRENCY
unset SIAGAPASOK_CONCURRENCY_DB_DATABASE

Production-like Frontend Build

Untuk memastikan frontend dapat dibuild:

npm run build

Asset hasil build akan dibuat oleh Vite.

Untuk mencoba aplikasi menggunakan built assets:

hentikan Vite dev server jika sedang berjalan;

jalankan Laravel:

php artisan serve

bila queue diperlukan:

php artisan queue:work

buka:

http://127.0.0.1:8000

Untuk kembali ke Hot Module Replacement development:

npm run dev

Update Repository Local

Jika repository sudah pernah di-clone dan Anda ingin mengambil perubahan terbaru:

git checkout master
git pull origin master

Setelah pull, dependency mungkin berubah.

Sinkronkan PHP dependency:

composer install

Sinkronkan Node dependency:

npm ci

Jalankan migration baru:

php artisan migrate

Bersihkan cache jika configuration berubah:

php artisan optimize:clear

Jika sedang memakai Demo Mode dan ada perubahan pada deterministic seeder:

php artisan db:seed

Kemudian jalankan kembali development server.

Troubleshooting

could not find driver

Contoh:

could not find driver

Biasanya PHP PostgreSQL driver belum aktif.

Pastikan:

pdo_pgsql
pgsql

tersedia pada:

php -m

Setelah mengubah php.ini, restart terminal/web server.

password authentication failed for user

Periksa:

DB_USERNAME=
DB_PASSWORD=

Pastikan sama dengan credential PostgreSQL local.

database "db_siagapasok" does not exist

Buat database terlebih dahulu:

createdb -U postgres db_siagapasok

atau gunakan pgAdmin.

No application encryption key has been specified

Jalankan:

php artisan key:generate

Vite / frontend tidak tampil atau manifest tidak ditemukan

Fresh clone:

npm ci

Development:

npm run dev

atau build:

npm run build

Perubahan .env tidak terbaca

Jalankan:

php artisan optimize:clear

Kemudian restart server.

Demo account tidak ditemukan

Pastikan:

SIAGAPASOK_DEMO_MODE=true

Kemudian:

php artisan optimize:clear
php artisan db:seed

Queue tidak memproses job

Jalankan:

php artisan queue:work

Atau gunakan:

composer run dev

yang sudah menjalankan queue listener bersama service development lain.

Port 8000 sudah dipakai

Gunakan port lain:

php artisan serve --port=8001

Kemudian sesuaikan bila perlu:

APP_URL=http://127.0.0.1:8001

Permission error pada Linux/macOS

Pastikan Laravel dapat menulis ke:

storage/
bootstrap/cache/

Contoh sesuai environment local Anda:

chmod -R ug+rwx storage bootstrap/cache

Jangan menggunakan permission global yang terlalu longgar pada production environment.

Batas Scope MVP

SiagaPasok berhenti pada orchestration dan readiness sebelum procurement.

Fitur berikut sengaja tidak menjadi bagian MVP:

vendor selection;

Purchase Order;

supplier bidding;

marketplace;

invoice;

payment;

financing;

lending;

receiving;

physical quality control;

farmer rating;

farmer scoring;

supplier ranking;

GPS/fleet tracking;

IoT;

blockchain;

AI/ML prediction;

menu nutrition calculation;

public government procurement API.

Jika project dikembangkan melewati MVP, penambahan domain tersebut sebaiknya dilakukan melalui revisi PRD/User Flow/ERD terlebih dahulu, bukan langsung ditambahkan ke source code.

Foundation Documents

Keputusan produk dan implementasi SiagaPasok didasarkan pada lima foundation documents yang tersedia di directory docs/.

Product Requirements Document

User Flow Specification

ERD Specification

Design System Specification

Modular Implementation Plan

Urutan conceptual authority:

PRD
 ↓
User Flow
 ↓
ERD
 ↓
Design System
 ↓
Modular Implementation Plan
 ↓
Implementation

Untuk memahami alasan di balik business rule seperti maker-checker, Safe Supply, Fallback, Readiness, dan RFP, baca dokumen tersebut sebelum melakukan perubahan domain yang material.

Catatan Keamanan dan Data Demo

Jangan commit .env

File .env dapat mengandung:

database password;

application key;

credential service lain.

Pastikan file tersebut tidak masuk Git.

Demo credentials bukan production credentials

Semua credential pada bagian Akun Demo merupakan credential publik dan deterministic.

Gunakan hanya untuk:

local development;

testing;

controlled presentation.

Jangan menggunakan main database untuk destructive test

Default main local database:

db_siagapasok

Jangan gunakan sebagai target:

migrate:fresh

untuk concurrency/rehearsal testing.

Gunakan database terpisah seperti:

db_siagapasok_concurrency_test

Demo data adalah simulasi

Nama organisasi demo, produsen, angka supply, lokasi, dan scenario dibuat untuk controlled simulation.

Demo tersebut tidak boleh ditafsirkan sebagai klaim kerja sama, deployment, performa operasional nyata, atau kondisi supply aktual.

Ringkasan Setup

Untuk pembaca yang sudah memahami Laravel/PostgreSQL dan hanya membutuhkan checklist, urutannya adalah:

1. Clone repository
2. Copy .env.example → .env
3. Buat PostgreSQL database db_siagapasok
4. Isi credential PostgreSQL pada .env
5. Set SIAGAPASOK_DEMO_MODE=true untuk evaluasi/demo
6. composer install
7. npm ci
8. php artisan key:generate
9. php artisan optimize:clear
10. php artisan migrate
11. php artisan db:seed
12. php artisan serve
13. npm run dev
14. php artisan queue:work
15. Buka http://127.0.0.1:8000/login

Atau setelah setup selesai:

composer run dev

SiagaPasok — Pasokan Lokal Siap Sebelum Pengadaan.