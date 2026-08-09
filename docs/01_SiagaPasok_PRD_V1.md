**SIAGAPASOK**

**Product Requirements Document (PRD)**

Pre-Procurement Supply Orchestration System untuk Rantai Pasok MBG

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>STATUS DOKUMEN</strong></p>
<p>DRAFT FOR REVIEW — Foundation Document 1 of 5. Dokumen ini mengunci kebutuhan produk dan business rules MVP sebelum User Flow, ERD, Design System, Modular Implementation Plan, dan implementasi dibuat.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

| **Item**                | **Keputusan**                                                                      |
|-------------------------|------------------------------------------------------------------------------------|
| Versi                   | 1.0                                                                                |
| Tanggal                 | 9 Agustus 2026                                                                     |
| Target                  | Working MVP lokal untuk demonstrasi BEC4 2026                                      |
| Stack arah implementasi | Laravel + Inertia.js + React + Vite; Shadcn UI diputuskan pada tahap Design System |
| Model akun              | Closed account; tidak ada public registration                                      |
| Scope MVP               | 1 SPPG + 2 KDKMP/Koperasi + ±15–20 produsen hortikultura                           |
| Status branding         | Belum dikunci; bukan bagian PRD                                                    |

# Daftar Isi

> 1\. Tujuan dan Kedudukan Dokumen
>
> 2\. Ringkasan Produk
>
> 3\. Problem, Root Cause, dan Product Objective
>
> 4\. Prinsip dan System Boundary
>
> 5\. Scope MVP dan Asumsi Pilot
>
> 6\. Aktor, Organisasi, dan Hak Akses
>
> 7\. Terminologi Domain
>
> 8\. End-to-End Product Flow
>
> 9\. Functional Requirements per Modul
>
> 10\. Business Rules dan Perhitungan
>
> 11\. State & Lifecycle Requirements
>
> 12\. Privacy, Audit, dan Notification
>
> 13\. Data Configuration & Controlled Simulation
>
> 14\. Non-Functional Requirements
>
> 15\. Acceptance Criteria
>
> 16\. Demo Scenario
>
> 17\. Out of Scope & Deferred Decisions
>
> 18\. Decision Ledger dan Traceability

# 1. Tujuan dan Kedudukan Dokumen

PRD ini menerjemahkan Final Concept Blueprint, Final Essay Architecture, hasil verifikasi operasional MBG, audit logika sistem, serta keputusan eksplisit proyek menjadi kebutuhan produk yang dapat digunakan sebagai dasar User Flow dan ERD. PRD tidak mendefinisikan schema database, desain visual final, atau struktur kode.

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>URUTAN WAJIB</strong></p>
<p>PRD → User Flow → ERD → Design System → Modular Implementation Plan → Implementasi. Tidak ada migration, model, controller, route, halaman React, atau komponen UI yang dibuat sebelum lima dokumen fondasi selesai.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

## 1.1 Hierarki Keputusan

Jika terdapat konflik, urutan otoritas yang digunakan dalam PRD ini adalah:

1.  Keputusan eksplisit pengguna pada percakapan proyek.

2.  Final Concept Blueprint yang berstatus LOCKED.

3.  Final Essay Architecture yang sudah mengaudit konflik evidence.

4.  Hasil verifikasi operasional dan audit logika terbaru.

5.  Riset dan hipotesis lama sebagai bahan konteks, bukan sumber keputusan otomatis.

## 1.2 Koreksi Penting terhadap Hasil Riset

| **Area**            | **Keputusan PRD**                                                                            |
|---------------------|----------------------------------------------------------------------------------------------|
| Fallback requester  | Tetap KDKMP requester: Operator menyiapkan, Manager requester approve broadcast; bukan SPPG. |
| Fallback acceptance | Tetap Manager KDKMP requester yang Accept/Reject offer; bukan SPPG.                          |
| Demand calculation  | Demand Target diinput langsung oleh SPPG. MVP tidak menjadi kalkulator AKG/BDD/menu.         |
| SPPG capacity       | Angka 2.500/3.000 hanya konteks regulasi; tidak menjadi hard limit aplikasi MVP.             |
| Planning horizon    | Tidak ada H-n nasional yang di-hard-code. Deadline/freshness harus configurable.             |
| Physical QC         | Dikeluarkan dari Logistics Readiness. QC fisik tetap di proses resmi SPPG/SIPGN.             |
| System Admin        | Tidak memiliki override supply atau Ready for Procurement.                                   |
| Badung/Bali         | Boleh menjadi setting seed/demo; bukan klaim deployment atau kemitraan nyata.                |

# 2. Ringkasan Produk

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>ONE-SENTENCE PRODUCT STATEMENT</strong></p>
<p>SiagaPasok adalah lapisan orkestrasi pasokan pra-pengadaan berbasis koperasi yang menerjemahkan forecast kebutuhan SPPG menjadi visibilitas pasokan kolektif produsen lokal, mendeteksi shortfall, dan memfasilitasi fallback lokal sebelum proses pengadaan resmi dimulai.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

## 2.1 Core Mechanism

**FORECAST → EXPECTED HARVEST → COMMIT → MONITOR → SAFE SUPPLY → SHORTFALL → FALLBACK → READINESS → READY FOR PROCUREMENT → FULFILMENT FEEDBACK**

## 2.2 Product Value

- Membuat kapasitas produsen yang tersebar terlihat sebagai satu status pasokan kolektif.

- Mengubah kondisi lapangan menjadi status risiko yang segera memengaruhi perhitungan pasokan.

- Mendeteksi kekurangan sebelum procurement final sehingga waktu recovery lebih panjang.

- Menghubungkan shortfall dengan fallback antar-KDKMP tanpa berubah menjadi marketplace.

- Menjaga keputusan supply melalui maker-checker, audit trail, dan pembatasan data antarorganisasi.

- Memberikan satu status Ready for Procurement yang bersifat derived dan selalu mencerminkan kondisi terbaru.

# 3. Problem, Root Cause, dan Product Objective

## 3.1 Problem Statement

Permintaan institusional MBG berskala besar dan berulang, sedangkan pasokan lokal berasal dari banyak produsen kecil dengan volume, waktu panen, risiko produksi, dan kesiapan logistik yang berbeda. Produsen individual sulit memenuhi kebutuhan institusional secara sendiri-sendiri; karena itu kapasitas harus dibaca dan dikoordinasikan secara kolektif melalui agregator seperti KDKMP/Koperasi.

## 3.2 Root Cause yang Dipilih

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>ROOT CAUSE</strong></p>
<p>Mismatch antara institutional demand berskala besar dengan fragmented local supply yang belum teragregasi dan terkoordinasi secara memadai pada horizon sebelum pengadaan.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

## 3.3 Product Objectives

| **ID** | **Objective**                                                                                           |
|--------|---------------------------------------------------------------------------------------------------------|
| O1     | Membuat forecast kebutuhan SPPG terlihat oleh KDKMP yang relevan sebelum procurement.                   |
| O2     | Mengagregasi expected harvest dan approved range commitment dari beberapa produsen.                     |
| O3     | Menghasilkan Safe Supply, At-Risk Supply, Coverage, dan Shortfall secara deterministik.                 |
| O4     | Memungkinkan risiko pasokan menurunkan confidence secara cepat tanpa menunggu approval.                 |
| O5     | Memungkinkan recovery melalui fallback antar-KDKMP yang source-backed dan anti-double-allocation.       |
| O6     | Menentukan Ready for Procurement melalui volume, logistics, dan document readiness seluruh contributor. |
| O7     | Merekam fulfilment pasca-proses resmi untuk menutup learning loop tanpa membuat farmer score.           |

# 4. Prinsip dan System Boundary

## 4.1 Boundary Statement

- Sistem DIMULAI ketika SPPG memiliki forecast kebutuhan komoditas untuk tanggal/periode mendatang.

- Sistem BERHENTI mengeksekusi pada status derived READY FOR PROCUREMENT.

- Setelah itu vendor selection, PO, receiving, stock, invoice, payment, dan proses resmi tetap berada di luar SiagaPasok.

- Jika kondisi memburuk sebelum procurement/fulfilment, sistem dapat kembali menunjukkan Shortfall dan mengaktifkan recovery.

- Setelah delivery selesai melalui proses resmi, SiagaPasok hanya menerima Fulfilment Feedback ringkas.

## 4.2 Explicit Non-Goals

| **Tidak Dibangun**         | **Batas**                                                                             |
|----------------------------|---------------------------------------------------------------------------------------|
| Marketplace / bidding      | Tidak ada price bidding, ranking seller, checkout, atau transaksi.                    |
| Procurement / PO           | Tidak membuat Purchase Order, vendor determination, invoice, atau pembayaran.         |
| Financing                  | Tidak ada lending, factoring, SCF, credit scoring, atau modal kerja.                  |
| AI/ML                      | MVP memakai deterministic rules; tidak ada prediction model atau farmer scoring.      |
| IoT / blockchain           | Tidak dibutuhkan untuk membuktikan mekanisme inti.                                    |
| Farm management            | Tidak mengatur pupuk, tanam, pestisida, kalender agronomi, atau hasil panen otomatis. |
| Physical QC                | Tidak menilai warna, rasa, organoleptik, kualitas fisik, atau penerimaan barang.      |
| Farmer portal              | Produsen tidak wajib login; data dikumpulkan oleh Operator/FRPL.                      |
| Government API integration | Tidak mengklaim SIPGN API. MVP stand-alone/manual input.                              |

# 5. Scope MVP dan Asumsi Pilot

| **Dimensi**     | **Scope MVP**                                                                                                    |
|-----------------|------------------------------------------------------------------------------------------------------------------|
| Deployment awal | Laptop/local only.                                                                                               |
| Organization    | 1 SPPG + 2 KDKMP/Koperasi.                                                                                       |
| Produsen        | ±15–20 produsen hortikultura total atau sesuai kebutuhan demo.                                                   |
| Komoditas seed  | Kangkung, bayam, kacang panjang; semua dapat diganti melalui master data.                                        |
| Lokasi seed     | Kabupaten Badung, Bali sebagai background controlled simulation; nama entitas dan orang tetap fiktif.            |
| Data input      | Manual; tidak bergantung pada API eksternal.                                                                     |
| Mode validasi   | Historical/controlled simulation terlebih dahulu; shadow pilot adalah tahap setelah MVP, bukan klaim deployment. |
| Bahasa          | Bahasa Indonesia utama; istilah Inggris sebagai secondary label/tooltip bila membantu.                           |
| Brand           | Belum dikunci. Green/Yellow/Red hanya semantic supply states, bukan warna brand utama.                           |

# 6. Aktor, Organisasi, dan Hak Akses

## 6.1 Account Model

- Closed account model; tidak ada public registration.

- User dibuat/diundang administrator.

- Untuk MVP: one user = one organization.

- Organization type hanya SPPG atau KDKMP.

- Produsen bukan user wajib; produsen adalah data internal organisasi KDKMP.

## 6.2 Actor Authority

| **Aktor**             | **Boleh**                                                                                                                                                                                 | **Dilarang**                                                                                                        |
|-----------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------|
| System Admin          | Kelola organisasi, user, role, aktivasi/deaktivasi akun; melihat metadata log platform.                                                                                                   | Tidak boleh membuat/mengubah supply, approve commitment, approve fallback, mengubah readiness, atau override RFP.   |
| SPPG User             | Membuat, publish, revisi, dan menutup/cancel forecast sesuai rule; melihat aggregate coverage, shortfall, contributor, readiness; mencatat fulfilment feedback.                           | Tidak melihat producer-level data; tidak membuat/approve supply commitment atau fallback offer.                     |
| KDKMP Operator / FRPL | Kelola producer registry; expected harvest; draft/submit commitment; downgrade confidence; submit recovery request; prepare fallback request/offer; prepare logistics/document readiness. | Tidak boleh approve keputusan sendiri, Accept offer sebagai requester, atau mengubah approved data secara langsung. |
| KDKMP Manager         | Approve/reject commitment & recovery; approve fallback broadcast; approve outgoing offer; Accept/Reject incoming offer; approve logistics/document readiness.                             | Tidak boleh menjadi maker untuk record yang sama; tidak boleh mengubah payload yang sedang direview.                |

## 6.3 Segregation of Duties

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>GOVERNANCE RULE</strong></p>
<p>Operator = INPUT / PREPARE. Manager = APPROVE / DECIDE. Sistem dapat menghitung otomatis, tetapi keputusan bisnis tidak boleh otomatis menggantikan approval Manager. Self-approval wajib ditolak di backend.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

# 7. Terminologi Domain

| **Istilah**           | **Definisi PRD**                                                                                                    |
|-----------------------|---------------------------------------------------------------------------------------------------------------------|
| Demand Forecast       | Kebutuhan komoditas yang diterbitkan SPPG untuk tanggal/periode tertentu.                                           |
| Expected Harvest      | Estimasi internal kapasitas panen; tidak mengikat dan tidak pernah dihitung sebagai Safe Supply.                    |
| Supply Commitment     | Kesanggupan pasokan range min–max yang disiapkan Operator dan disetujui Manager.                                    |
| Supply Confidence     | Status satu commitment: GREEN, YELLOW, RED. Bukan reputasi produsen.                                                |
| Safe Supply           | Jumlah minimum volume dari approved GREEN commitments dan accepted fallback yang underlying supply-nya tetap valid. |
| At-Risk Supply        | Minimum volume dari approved YELLOW commitments; terlihat tetapi tidak dihitung ke Safe Supply.                     |
| Coverage              | Rasio Safe Supply terhadap Demand Target.                                                                           |
| Shortfall             | max(0, Demand Target − Safe Supply).                                                                                |
| Fallback Request      | Permintaan bantuan pasokan agregat dari KDKMP yang mengalami shortfall.                                             |
| Fallback Offer        | Penawaran source-backed dari KDKMP lain untuk memenuhi fallback request.                                            |
| Contributor           | KDKMP yang memiliki kontribusi Safe Supply \> 0 untuk forecast tersebut.                                            |
| Ready for Procurement | Derived property yang TRUE hanya jika Volume Ready dan seluruh contributor memenuhi Logistics + Document Ready.     |
| Fulfilment Feedback   | Catatan plan-vs-delivery setelah proses resmi selesai; tidak menghasilkan farmer score.                             |

# 8. End-to-End Product Flow

| **Step** | **Flow**                                                                                                                                            |
|----------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| 1        | SPPG membuat Demand Forecast dalam DRAFT, lalu PUBLISH.                                                                                             |
| 2        | KDKMP Operator melihat forecast agregat yang relevan dan mengelola Expected Harvest produsen internal.                                              |
| 3        | Operator menyiapkan range Supply Commitment dan submit.                                                                                             |
| 4        | KDKMP Manager approve/reject. Approved pertama kali menjadi GREEN.                                                                                  |
| 5        | Sistem menghitung Safe Supply, At-Risk Supply, Coverage, dan Shortfall.                                                                             |
| 6        | Operator memperbarui kondisi. Downgrade GREEN→YELLOW/RED langsung efektif dan diaudit.                                                              |
| 7        | Jika Shortfall \> 0, Operator requester menyiapkan Fallback Request; Manager requester approve broadcast.                                           |
| 8        | KDKMP lain melihat request agregat. Operator supplier menyiapkan source-backed Offer; Manager supplier approve sehingga capacity di-reserve atomik. |
| 9        | Manager requester Accept/Reject. Hanya accepted volume yang dapat berkontribusi pada Safe Supply; reserve yang tidak terpakai dilepas.              |
| 10       | Semua KDKMP contributor menyiapkan Logistics dan Document Readiness; Manager masing-masing approve.                                                 |
| 11       | Sistem menghitung READY FOR PROCUREMENT secara real-time. Tidak ada tombol manual.                                                                  |
| 12       | Proses pengadaan resmi berlangsung di luar SiagaPasok.                                                                                              |
| 13       | SPPG mencatat Fulfilment Feedback per contributor setelah delivery.                                                                                 |

# 9. Functional Requirements per Modul

| **ID / Modul**                    | **Kebutuhan Minimum**                                                                                                              |
|-----------------------------------|------------------------------------------------------------------------------------------------------------------------------------|
| FR-01 Authentication & Account    | Login closed-account; no public registration; role & organization context selalu diterapkan.                                       |
| FR-02 Organization Administration | Admin membuat/menonaktifkan SPPG/KDKMP dan user. Hard delete operational organization tidak diperlukan pada MVP.                   |
| FR-03 Commodity Master            | Master komoditas dan unit. Seed: kangkung, bayam, kacang panjang. Parameter agronomi hanya informasi opsional.                     |
| FR-04 Demand Forecast             | SPPG create/edit/publish forecast: commodity, target volume, unit, required date/period, deadline/freshness configuration, notes.  |
| FR-05 Producer Registry           | KDKMP menyimpan produsen internal; data organization-scoped. Tidak ada public farmer login.                                        |
| FR-06 Expected Harvest            | Operator create/update estimasi panen per producer/commodity/window. Tidak masuk Safe Supply.                                      |
| FR-07 Supply Commitment           | Operator membuat min/max commitment dan submit; Manager approve/reject; approved version immutable, revision versioned.            |
| FR-08 Confidence Monitoring       | Downgrade cepat oleh Operator/System; recovery ke GREEN membutuhkan Manager approval; RED terminal.                                |
| FR-09 Supply Calculation          | Sistem menghitung Safe Supply, At-Risk, Coverage, Shortfall, Surplus secara derived.                                               |
| FR-10 Fallback Request            | Operator requester prepare/submit; Manager requester approve broadcast; hanya data agregat yang dibuka.                            |
| FR-11 Fallback Offer              | Operator supplier membuat source-backed offer; Manager supplier approve; capacity reserve atomik; Manager requester Accept/Reject. |
| FR-12 Logistics Readiness         | Checklist forecast/delivery-specific; Operator prepare; Manager approve; edit setelah approval menginvalidasi approval.            |
| FR-13 Document Readiness          | Validitas org-level + forecast-specific requirement; Operator prepare; Manager approve; expiry/revision menginvalidasi readiness.  |
| FR-14 Ready for Procurement       | Derived real-time dari rule multi-supplier; tidak disimpan sebagai toggle manual.                                                  |
| FR-15 Notifications               | In-app, action-oriented; maksimal set prioritas inti pada MVP.                                                                     |
| FR-16 Audit Trail                 | Mencatat actor, org, action, entity, before/after, timestamp, reason bila relevan.                                                 |
| FR-17 Fulfilment Feedback         | SPPG mencatat delivered volume/date/result/reason per contributor; tidak memengaruhi farmer score.                                 |
| FR-18 Demo Scenario Control       | Mode demo dapat memicu controlled disruption / time progression tanpa menyamar sebagai data lapangan.                              |

## 9.1 Demand Forecast Requirements

- Demand Target adalah input volume operasional langsung dari SPPG; bukan hasil kalkulator AKG/BDD di MVP.

- Forecast memiliki commodity, target volume, unit, required date/period, notes, dan status lifecycle.

- Planning lead-time tidak di-hard-code. Freshness/update interval dapat dikonfigurasi pada forecast/demo.

- Setelah PUBLISHED, setiap revisi volume/tanggal menghasilkan audit event dan recalculation seluruh metrik terkait.

- Forecast tidak boleh di-cancel jika masih ada accepted fallback allocation yang belum diselesaikan; MVP menampilkan conflict dan meminta resolusi manual sebelum cancel.

## 9.2 Expected Harvest Requirements

- Expected Harvest adalah data internal KDKMP dan tidak terlihat detailnya oleh SPPG/KDKMP lain.

- Operator dapat memperbarui estimasi tanpa Manager approval.

- MVP menerima estimated range dan harvest window secara manual; tidak menghitung yield otomatis dari luas lahan.

- Jika proposed commitment melampaui expected harvest, sistem memberi soft warning + meminta justification, bukan hard block.

- Expected Harvest tidak pernah masuk formula Safe Supply.

## 9.3 Commitment & Revision Requirements

- Commitment wajib memiliki minimum volume ≤ maximum volume, commodity, required date/window, sumber producer/aggregation reference, dan notes.

- Initial approval oleh Manager membuat commitment operasional aktif dan confidence GREEN.

- Approved payload tidak boleh diedit in-place; perubahan membuat revision/version baru.

- Jika revisi disebabkan risiko, confidence commitment aktif wajib turun ke YELLOW/RED sebelum revision diproses sehingga volume lama tidak terus dihitung aman.

- Approval revision tidak otomatis mengembalikan GREEN kecuali Manager juga mengesahkan recovery. Tanpa recovery approval, revised commitment tetap tidak masuk Safe Supply.

- RED bersifat terminal. Jika kapasitas baru muncul setelah RED, buat commitment baru.

## 9.4 Confidence Requirements

| **Status** | **Makna**                                                                                 | **Efek**                                |
|------------|-------------------------------------------------------------------------------------------|-----------------------------------------|
| GREEN      | Minimum commitment masih dipercaya tersedia pada waktu yang dibutuhkan.                   | Masuk Safe Supply.                      |
| YELLOW     | Ada risiko, data stale, volume/tanggal belum pasti, atau revision/recovery belum selesai. | Keluar dari Safe Supply; masuk At-Risk. |
| RED        | Komitmen tidak dapat dipenuhi / gagal definitif.                                          | Tidak dihitung; terminal.               |

- Downgrade GREEN→YELLOW/RED dan YELLOW→RED dapat dilakukan Operator atau System dan langsung efektif.

- Setiap downgrade wajib memiliki reason/event dan audit trail.

- YELLOW→GREEN membutuhkan Recovery Request dari Operator dan approval Manager.

- Stale GREEN otomatis menjadi YELLOW setelah melewati freshness interval yang dikonfigurasi untuk forecast.

# 10. Business Rules dan Perhitungan

## 10.1 Supply Formula

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>SAFE SUPPLY</strong></p>
<p>Safe Supply = Σ minimum volume dari APPROVED + GREEN internal commitments + accepted fallback contribution yang underlying supply-nya tetap APPROVED + GREEN dan belum expired/cancelled.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>AT-RISK SUPPLY</strong></p>
<p>At-Risk Supply = Σ minimum volume dari APPROVED + YELLOW commitments. Nilai ini ditampilkan terpisah dan tidak mengurangi Shortfall.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>SHORTFALL</strong></p>
<p>Shortfall = max(0, Demand Target − Safe Supply).</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>COVERAGE</strong></p>
<p>Coverage % = jika Demand Target &gt; 0 maka min(100, Safe Supply / Demand Target × 100). Surplus ditampilkan sebagai nilai terpisah; tidak perlu Coverage &gt;100%.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

## 10.2 Volume Ready

Volume Ready = TRUE jika Safe Supply ≥ Demand Target. Nilai ini dihitung sistem. Jika Safe Supply turun atau demand naik, Volume Ready otomatis kembali FALSE.

## 10.3 Fallback Capacity Rules

- Fallback Offer tidak boleh berupa angka bebas tanpa sumber. Offer harus dibackup oleh eligible surplus Safe Supply internal KDKMP supplier.

- Pada saat Manager supplier approve Offer → AVAILABLE, offered volume di-reserve secara atomik.

- Reserve tidak boleh membuat available fallback capacity negatif atau melebihi surplus eligible.

- Manager requester dapat Accept seluruh atau sebagian offered volume. Accepted portion menjadi allocated; sisa reserve dilepas.

- REJECTED, WITHDRAWN, dan EXPIRED melepaskan reserve.

- ACCEPTED tidak boleh ditarik sepihak. Namun underlying biological supply tetap dapat turun ke YELLOW/RED; bila itu terjadi contribution keluar dari Safe Supply dan Shortfall dapat muncul kembali.

- Expiry wajib ada dan tidak boleh melewati fallback request response deadline/required date operational boundary. Nilainya configurable, bukan 24 jam hard-coded.

## 10.4 Multi-Supplier Ready for Procurement

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>FINAL RFP RULE</strong></p>
<p>READY FOR PROCUREMENT = Volume Ready TRUE AND setiap KDKMP contributor memiliki Logistics Ready APPROVED AND Document Ready VALID/APPROVED. Contributor = organisasi dengan kontribusi Safe Supply &gt; 0 pada forecast.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

- RFP adalah derived property; tidak ada kolom/toggle manual sebagai source of truth.

- Jika contributor fallback B memasok 55 kg dari offer 60 kg, B tetap contributor dan readiness B wajib valid.

- RFP otomatis FALSE ketika supply degradasi, commitment dibatalkan/expired, demand naik, logistics/document approval invalid, atau required date boundary terlewati.

## 10.5 Demand Revision

| **Perubahan**                          | **Efek Sistem**                                                                                                                                     |
|----------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| Demand naik                            | Safe Supply tetap; Coverage turun; Shortfall dapat muncul. Fallback request baru dapat dibuat jika perlu. Terminal request lama tidak dibuka ulang. |
| Demand turun                           | Coverage naik dan surplus muncul. Existing accepted fallback tidak dibatalkan otomatis.                                                             |
| Demand turun di bawah allocated supply | Tampilkan surplus dan notifikasi; tidak ada auto-release accepted allocation.                                                                       |
| Cancel forecast                        | Boleh jika belum ada accepted fallback allocation. Jika ada, sistem blok dan meminta resolusi eksternal/manual terlebih dahulu.                     |

# 11. State & Lifecycle Requirements

## 11.1 Forecast Lifecycle

| **State** | **Allowed Next**     | **Keterangan**                                                        |
|-----------|----------------------|-----------------------------------------------------------------------|
| DRAFT     | PUBLISHED, CANCELLED | Belum terlihat KDKMP.                                                 |
| PUBLISHED | CLOSED, CANCELLED\*  | Aktif untuk orkestrasi. \*Cancel dibatasi jika ada accepted fallback. |
| CLOSED    | —                    | Terminal; required period selesai.                                    |
| CANCELLED | —                    | Terminal.                                                             |

## 11.2 Commitment Lifecycle

| **State**        | **Allowed Next**                     | **Keterangan**                                             |
|------------------|--------------------------------------|------------------------------------------------------------|
| DRAFT            | PENDING_APPROVAL, CANCELLED          | Editable oleh Operator.                                    |
| PENDING_APPROVAL | APPROVED, REJECTED                   | Manager read-only reviewer.                                |
| REJECTED         | DRAFT (new revision)                 | Alasan wajib; tidak langsung ke APPROVED.                  |
| APPROVED         | PENDING_REVISION, CANCELLED, EXPIRED | Payload aktif immutable.                                   |
| PENDING_REVISION | APPROVED revised / REJECTED revision | Confidence existing harus YELLOW/RED; Safe contribution 0. |
| CANCELLED        | —                                    | Terminal.                                                  |
| EXPIRED          | —                                    | Terminal.                                                  |

## 11.3 Confidence Lifecycle

| **Current** | **Transition**     | **Authority**                                           |
|-------------|--------------------|---------------------------------------------------------|
| GREEN       | → YELLOW / RED     | Operator atau System; langsung efektif.                 |
| YELLOW      | → RED              | Operator; langsung efektif.                             |
| YELLOW      | → GREEN            | Operator request recovery → Manager approve.            |
| RED         | Tidak ada recovery | Terminal; buat commitment baru jika supply baru muncul. |

## 11.4 Fallback Request Lifecycle

| **State**        | **Allowed Next**              | **Rule**                                                           |
|------------------|-------------------------------|--------------------------------------------------------------------|
| DRAFT            | PENDING_APPROVAL, CANCELLED   | Dibuat Operator requester saat Shortfall \> 0.                     |
| PENDING_APPROVAL | OPEN, REJECTED                | Manager requester approve/reject.                                  |
| OPEN             | FULFILLED, EXPIRED, CANCELLED | Tetap OPEN walau partial; remaining_shortfall ditampilkan derived. |
| FULFILLED        | —                             | Terminal ketika accepted fallback memenuhi request target.         |
| EXPIRED          | —                             | Terminal saat response deadline lewat.                             |
| CANCELLED        | —                             | Terminal; Manager requester.                                       |

## 11.5 Fallback Offer Lifecycle

| **State**        | **Allowed Next**                       | **Capacity Effect**                                                          |
|------------------|----------------------------------------|------------------------------------------------------------------------------|
| DRAFT            | PENDING_APPROVAL, WITHDRAWN            | Belum reserve.                                                               |
| PENDING_APPROVAL | AVAILABLE, REJECTED                    | Manager supplier review.                                                     |
| AVAILABLE        | ACCEPTED, REJECTED, WITHDRAWN, EXPIRED | Reserve aktif.                                                               |
| ACCEPTED         | —                                      | Accepted portion allocated; unused reserve released. Commercial state final. |
| REJECTED         | —                                      | Reserve released.                                                            |
| WITHDRAWN        | —                                      | Hanya sebelum Accept; reserve released.                                      |
| EXPIRED          | —                                      | Reserve released.                                                            |

# 12. Privacy, Audit, dan Notification

## 12.1 Data Visibility

| **Data**                                    | **Visibility**                                                                                                           |
|---------------------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| Producer profile & expected harvest KDKMP A | KDKMP A only                                                                                                             |
| Individual commitments KDKMP A              | KDKMP A only; SPPG hanya agregat                                                                                         |
| Fallback broadcast                          | KDKMP network: commodity, shortage volume, required period/date, response deadline, requester organization               |
| Fallback supplier internals                 | Hanya KDKMP supplier; requester tidak melihat producer sumber                                                            |
| SPPG dashboard                              | Demand, contributor organizations, aggregate Safe/At-Risk/Shortfall, readiness, fulfilment                               |
| System Admin                                | Account/org administration dan platform audit metadata; tidak melihat operational payload detail sebagai kebutuhan rutin |

## 12.2 Audit Trail

Audit event minimum menyimpan: timestamp, actor_id, actor_role, organization_id, action, entity_type, entity_id, previous_value, new_value, reason_note bila relevan, dan source (USER/SYSTEM).

- Demand create/publish/revise/cancel.

- Expected Harvest material update.

- Commitment submit/approve/reject/revision/cancel.

- Confidence downgrade, stale auto-downgrade, recovery request/approval.

- Fallback request submit/approve/cancel/expire.

- Fallback offer submit/approve/reserve/accept/reject/withdraw/expire/release.

- Logistics/Document submit/approve/reject/invalidate.

- RFP transition FALSE↔TRUE sebagai calculated audit event.

- Fulfilment feedback recorded/updated.

- Admin user/organization/role changes.

## 12.3 In-App Notifications

| **ID** | **Notification**                                                                                          |
|--------|-----------------------------------------------------------------------------------------------------------|
| N1     | Manager: Commitment/Recovery/Fallback/Readiness membutuhkan approval.                                     |
| N2     | Operator + Manager: GREEN commitment menjadi YELLOW/RED.                                                  |
| N3     | Operator: commitment stale dan perlu verifikasi.                                                          |
| N4     | Operator + Manager requester: Shortfall terdeteksi / membesar.                                            |
| N5     | KDKMP supplier: Fallback Request baru yang relevan.                                                       |
| N6     | Manager requester: Fallback Offer AVAILABLE dan perlu Accept/Reject.                                      |
| N7     | Operator + Manager: Logistics/Document approval terinvalidasi atau belum lengkap mendekati required date. |
| N8     | SPPG + contributor managers: RFP tercapai atau hilang.                                                    |

# 13. Data Configuration & Controlled Simulation

## 13.1 Parameter yang Configurable

- Commodity dan unit.

- Demand target dan required date/period.

- Forecast response deadline / freshness interval.

- Fallback request deadline dan offer expiry (bounded by request/required date).

- Logistics checklist items.

- Document requirement checklist dan masa berlaku.

- Demo location / organization / producer names.

- SPPG beneficiary/capacity metadata jika ingin dicatat; tidak digunakan sebagai hard compliance engine.

## 13.2 Controlled Simulation

- Nama SPPG, KDKMP, produsen, nomor kontak, dan commitment individual adalah data simulasi kecuali ada mitra nyata.

- Demo demand (misalnya ratusan kg satu komoditas) adalah seed scenario, bukan klaim kebutuhan nasional.

- Gangguan cuaca/hama dan perubahan volume dalam demo adalah controlled event yang harus diberi label “SIMULASI”.

- Parameter hortikultura boleh memakai research-based ranges untuk membuat skenario realistis, tetapi aplikasi tidak mengklaim melakukan prediksi agronomi.

# 14. Non-Functional Requirements

| **ID**                 | **Requirement**                                                                                                                |
|------------------------|--------------------------------------------------------------------------------------------------------------------------------|
| NFR-01 Security        | Semua route/action wajib role + organization scoped; self-approval ditolak backend.                                            |
| NFR-02 Data Isolation  | KDKMP tidak dapat mengakses detail produsen organisasi lain melalui UI maupun direct URL/API.                                  |
| NFR-03 Integrity       | Acceptance/reservation/approval harus atomic dan idempotent untuk mencegah double allocation dan double submit.                |
| NFR-04 Derived Metrics | Safe Supply, Coverage, Shortfall, Volume Ready, RFP tidak boleh bergantung pada manual cached flag sebagai source of truth.    |
| NFR-05 Auditability    | Critical state transitions memiliki immutable audit trail.                                                                     |
| NFR-06 Explainability  | Setiap angka dashboard dapat ditelusuri ke contributing commitments/accepted fallback bagi user yang berwenang.                |
| NFR-07 Usability       | Bahasa Indonesia utama; status dan action harus mudah dipahami operator non-teknis.                                            |
| NFR-08 Local Demo      | Aplikasi dapat berjalan local tanpa dependency eksternal mandatory.                                                            |
| NFR-09 Time Handling   | Timestamp konsisten dan required/expiry dates dievaluasi server-side.                                                          |
| NFR-10 Performance     | MVP harus responsif pada skala pilot (±20 produsen, beberapa forecast/commodity, ratusan event audit) tanpa optimasi kompleks. |

# 15. Acceptance Criteria

1\. System Admin dapat membuat organization dan user tanpa membuka public registration.

2\. User hanya dapat beroperasi dalam organization yang ditetapkan.

3\. SPPG dapat membuat, publish, dan merevisi Demand Forecast.

4\. KDKMP Operator dapat membuat producer dan Expected Harvest.

5\. Operator dapat membuat range commitment dan submit.

6\. Manager dapat approve/reject commitment dan self-approval ditolak.

7\. Sistem menghitung Safe Supply hanya dari minimum approved GREEN commitments.

8\. YELLOW tampil sebagai At-Risk dan tidak masuk Safe Supply.

9\. GREEN dapat downgrade instan; recovery ke GREEN memerlukan Manager approval.

10\. Stale GREEN dapat otomatis menjadi YELLOW berdasarkan configurable freshness interval.

11\. Shortfall muncul otomatis ketika Safe Supply \< Demand Target.

12\. Operator requester dapat membuat Fallback Request; Manager requester approve broadcast.

13\. KDKMP supplier hanya melihat broadcast aggregate, bukan producer requester.

14\. Operator supplier dapat membuat source-backed Offer; Manager supplier approve dan capacity di-reserve atomik.

15\. Manager requester dapat Accept/Reject offer; hanya accepted portion yang berkontribusi pada Safe Supply.

16\. Double allocation dan acceptance terhadap expired/non-available offer ditolak backend.

17\. Underlying fallback yang menjadi YELLOW/RED mengurangi Safe Supply requester dan dapat memunculkan Shortfall baru.

18\. Operator menyiapkan Logistics dan Document Readiness; Manager approve; edit/expiry menginvalidasi approval.

19\. RFP hanya TRUE jika volume cukup dan seluruh contributor memenuhi logistics + documents.

20\. RFP otomatis kembali FALSE saat salah satu dependency memburuk.

21\. SPPG melihat aggregate readiness tanpa producer-level data.

22\. Fulfilment feedback dapat dicatat per contributor tanpa PO/payment/QC dan tidak membuat farmer score.

23\. Demo disruption dapat menunjukkan Covered → Shortfall → Fallback → Covered → Ready for Procurement secara end-to-end.

24\. Semua critical actions tercatat pada audit trail.

# 16. Demo Scenario

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>LABEL</strong></p>
<p>Seluruh angka dan nama di bagian ini adalah CONTROLLED DEMO SIMULATION. Tujuannya menguji state transition, bukan menyatakan data lapangan aktual.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

| **Step** | **Controlled Demo**                                                                                                                                 |
|----------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| 1        | SPPG Badung Demo publish forecast Kangkung 400 kg untuk tanggal X.                                                                                  |
| 2        | KDKMP Tani Sejahtera memiliki approved GREEN commitments total minimum 400 kg → Coverage 100%, Shortfall 0.                                         |
| 3        | Controlled disruption: satu commitment 150 kg didowngrade GREEN→YELLOW karena gangguan cuaca → Safe Supply turun menjadi 250 kg → Shortfall 150 kg. |
| 4        | Operator KDKMP requester membuat Fallback Request 150 kg; Manager requester approve broadcast.                                                      |
| 5        | KDKMP Mitra Lestari menyiapkan Offer 160 kg dari eligible surplus; Manager supplier approve → AVAILABLE, 160 kg di-reserve.                         |
| 6        | Manager requester Accept 150 kg; 150 kg menjadi allocated, sisa 10 kg reserve dilepas.                                                              |
| 7        | Safe Supply kembali 400 kg → Volume Ready TRUE.                                                                                                     |
| 8        | Kedua KDKMP contributor menyelesaikan Logistics dan Document Readiness; Manager masing-masing approve.                                              |
| 9        | RFP menjadi TRUE secara derived dan terlihat oleh SPPG.                                                                                             |
| 10       | Setelah proses resmi di luar sistem, SPPG mencatat fulfilment per contributor. Jika delivery kurang, result menjadi PARTIAL dan reason wajib.       |

# 17. Out of Scope & Deferred Decisions

## 17.1 Tidak Masuk MVP

- Integrasi SIPGN/API pemerintah.

- Automated menu/AKG/BDD calculator.

- Farmer self-service/mobile app.

- Pricing, bidding, contract, PO, payment, invoicing.

- Financing/credit.

- Physical QC/food safety inspection.

- AI forecasting/recommendation/reliability score.

- Fleet tracking/GPS.

- Automated agronomic yield calculation.

- Legal dispute/compensation workflow untuk accepted fallback yang dibatalkan akibat perubahan demand.

## 17.2 MVP Policy untuk Edge Case yang Belum Memiliki Standar Universal

| **Area**                               | **Keputusan MVP**                                                                                                                         |
|----------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|
| Stale threshold                        | Configurable per forecast. Seed demo dapat memakai 7 hari; bukan standar MBG.                                                             |
| Offer expiry                           | Configurable dan bounded oleh fallback request deadline/required date; tidak ada default hukum 24 jam.                                    |
| Demand turun setelah accepted fallback | Accepted allocation tetap; tampilkan surplus. Tidak ada auto-cancel.                                                                      |
| Force majeure admin override           | Tidak ada System Admin override supply/RFP pada MVP.                                                                                      |
| Shortfall severity                     | Tidak menggunakan LOW/MEDIUM/CRITICAL threshold arbitrer pada MVP; tampilkan volume, coverage, dan time-to-required-date.                 |
| Accepted fallback cancellation         | Tidak ada unilateral cancellation. Konflik komersial/force majeure ditangani di luar MVP; risk degradation tetap memengaruhi Safe Supply. |

# 18. Decision Ledger dan Traceability

| **ID** | **Decision**                                                                             | **Status**                |
|--------|------------------------------------------------------------------------------------------|---------------------------|
| D-01   | KDKMP/Koperasi primary supply user & decision owner.                                     | LOCKED                    |
| D-02   | SPPG demand owner dan official procurement actor.                                        | LOCKED                    |
| D-03   | Closed account; one user = one organization pada MVP.                                    | LOCKED                    |
| D-04   | Producer tidak wajib memiliki interface.                                                 | LOCKED                    |
| D-05   | Operator prepare/input; Manager approve/decide; no self-approval.                        | LOCKED                    |
| D-06   | Range commitment min/max.                                                                | LOCKED                    |
| D-07   | GREEN dihitung Safe; YELLOW At-Risk; RED unavailable.                                    | LOCKED                    |
| D-08   | Downgrade instan; recovery ke GREEN perlu Manager approval.                              | ADOPTED FROM LOGIC AUDIT  |
| D-09   | Safe Supply memakai lower bound; Shortfall derived.                                      | LOCKED                    |
| D-10   | Fallback request dibuat KDKMP requester; Manager requester approve.                      | LOCKED / USER DECISION    |
| D-11   | Outgoing offer Manager supplier approve; incoming offer Manager requester Accept/Reject. | LOCKED / USER DECISION    |
| D-12   | Fallback source-backed + atomic reservation; accepted portion only.                      | ADOPTED FROM LOGIC AUDIT  |
| D-13   | RFP derived, multi-supplier readiness wajib.                                             | ADOPTED / LOCKED          |
| D-14   | System berhenti di RFP; fulfilment feedback sesudah proses resmi.                        | LOCKED                    |
| D-15   | No PO/payment/financing/AI/IoT/blockchain/physical QC.                                   | LOCKED                    |
| D-16   | Planning horizon & freshness configurable, bukan H-n hard-coded.                         | VERIFIED                  |
| D-17   | Demand target direct input; no AKG/BDD calculator MVP.                                   | VERIFIED / BOUNDARY       |
| D-18   | Demo Badung/Bali = controlled simulation context, bukan deployment claim.                | RESEARCH-INFORMED         |
| D-19   | No shortfall severity threshold arbitrer pada MVP.                                       | PRD SIMPLIFICATION        |
| D-20   | Tech target local: Laravel + Inertia + React + Vite; Shadcn setelah Design System.       | IMPLEMENTATION CONSTRAINT |

## 18.1 Dokumen Dasar yang Digunakan

- Final Concept Blueprint SiagaPasok — locked baseline konsep, boundary, actor, pilot, dan KPI.

- Final Essay Architecture — conflict resolution dan evidence discipline.

- Riset Operasional MVP SiagaPasok — verifikasi parameter MBG dan batas fakta operasional.

- Audit Logika Sistem Rantai Pasok — state integrity, maker-checker, reservation, RFP derivation, dan edge cases.

- Keputusan eksplisit pengguna mengenai closed account, organization scope, readiness, fallback acceptance, bahasa/branding, dan local deployment.

## 18.2 Exit Criteria untuk Berlanjut ke Dokumen 2 — User Flow

- Product scope dan boundary disetujui.

- Aktor dan authority matrix disetujui.

- Formula Safe Supply/Shortfall/RFP disetujui.

- Fallback requester/offer/acceptance flow disetujui.

- State lifecycle utama tidak memiliki konflik terbuka.

- Out-of-scope dan deferred edge cases diterima.

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><p><strong>NEXT DOCUMENT</strong></p>
<p>Setelah PRD ini disetujui, Dokumen 2 adalah USER FLOW: alur layar per role, happy path, approval path, fallback path, risk/recovery path, readiness path, fulfilment path, serta exception flow.</p></th>
</tr>
</thead>
<tbody>
</tbody>
</table>
