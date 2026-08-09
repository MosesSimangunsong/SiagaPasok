# SIAGAPASOK

## Design System Specification

**Pre-Procurement Supply Orchestration System untuk Rantai Pasok MBG**

> **STATUS DOKUMEN**  
> DRAFT FOR REVIEW — Foundation Document 4 of 5.  
> Dokumen ini menerjemahkan `01_SiagaPasok_PRD_V1.md`, `02_SiagaPasok_User_Flow_V1.md`, dan `03_SiagaPasok_ERD_V1.md` menjadi aturan visual, interaction pattern, component policy, information hierarchy, semantic state, dan screen composition untuk working MVP SiagaPasok. Dokumen ini **belum** merupakan implementasi React/Tailwind/shadcn.

| Item | Keputusan |
|---|---|
| Versi | 1.0 |
| Tanggal | 9 Agustus 2026 |
| Dokumen sebelumnya | `01_SiagaPasok_PRD_V1.md`, `02_SiagaPasok_User_Flow_V1.md`, `03_SiagaPasok_ERD_V1.md` |
| Dokumen berikutnya | `05_SiagaPasok_Modular_Implementation_Plan_V1.md` |
| Target UI | Internal operational web application; laptop-first |
| Bahasa utama | Bahasa Indonesia |
| Secondary terminology | Bahasa Inggris hanya sebagai secondary label/tooltip bila membantu istilah domain |
| UI component system | **shadcn/ui sebagai primary component system** |
| Styling direction | Tailwind utility untuk layout/token application; tidak memakai component library lain |
| Brand direction | Navy + Cobalt Blue; bukan Green/Yellow/Red |
| Semantic supply states | Green = Aman, Amber = Berisiko, Red = Kritis |
| Accessibility | Status tidak pernah disampaikan hanya dengan warna |
| Visual character | Modern, serius, operasional, data-driven, government-friendly, tidak gimmicky |

---

# 1. Tujuan Dokumen

Design System ini memastikan setiap halaman SiagaPasok memiliki bahasa visual dan pola interaksi yang konsisten, terutama pada kondisi yang berisiko menghasilkan salah tafsir operasional.

Sistem desain harus membantu pengguna menjawab lima pertanyaan utama dengan cepat:

1. **Apa kebutuhan yang harus dipenuhi?**
2. **Berapa pasokan yang benar-benar aman saat ini?**
3. **Apakah terdapat pasokan berisiko atau shortfall?**
4. **Tindakan siapa yang sedang dibutuhkan?**
5. **Apakah seluruh contributor sudah siap menuju procurement resmi?**

Design System tidak boleh membuat status supply terlihat lebih aman daripada business truth di backend.

## 1.1 Hierarki Keputusan

Jika terdapat konflik pada tahap implementasi UI, gunakan urutan berikut:

1. keputusan eksplisit pengguna;
2. `01_SiagaPasok_PRD_V1.md`;
3. `02_SiagaPasok_User_Flow_V1.md`;
4. `03_SiagaPasok_ERD_V1.md`;
5. dokumen Design System ini untuk keputusan visual/interaksi;
6. preferensi estetika implementasi sebagai pilihan terakhir.

## 1.2 Perubahan Status Branding

PRD menyatakan branding belum dikunci. Dokumen ini menjadi titik penguncian arah visual MVP.

**Keputusan:**

- Brand utama tidak menggunakan hijau, kuning/amber, atau merah karena warna tersebut sudah memiliki makna supply confidence.
- Arah brand menggunakan **Deep Navy + Cobalt Blue** untuk memberi kesan kepercayaan, koordinasi, visibilitas data, dan institusional tanpa terlihat seperti aplikasi perbankan atau trading.
- Custom logo ilustratif **tidak wajib** untuk MVP. Wordmark SiagaPasok yang rapi lebih diprioritaskan daripada membuat logo generik yang tidak menambah nilai.

---

# 2. Design Principles

## 2.1 Operational Truth Before Decoration

UI harus memprioritaskan state bisnis terbaru. Elemen dekoratif tidak boleh mengalahkan informasi seperti Shortfall, At-Risk Supply, pending approval, atau invalidated readiness.

## 2.2 Conservative Visual Semantics

Jika supply tidak masuk Safe Supply, UI tidak boleh memberi kesan supply tersebut sudah tersedia.

Contoh:

- YELLOW ditampilkan sebagai **Berisiko**, bukan “Hampir Aman”.
- Offer `AVAILABLE` ditampilkan sebagai **Tersedia untuk Diputuskan**, bukan “Pasokan Tambahan”.
- Offer baru menjadi kontribusi setelah `ACCEPTED` dan underlying source tetap valid.

## 2.3 Actionability Over Information Volume

Setiap halaman operasional harus memperlihatkan next action yang jelas.

Contoh:

- `Shortfall 55 kg` → CTA `Siapkan Fallback Request`.
- `3 Komitmen Menunggu Persetujuan` → CTA `Buka Approval Queue`.
- `Kesiapan Logistik Tidak Valid` → CTA `Perbarui Kesiapan`.

## 2.4 Role-Aware Simplicity

Setiap role hanya melihat navigasi dan aksi yang relevan. Jangan membuat satu super-dashboard yang menampilkan semua fitur lalu menonaktifkan sebagian besar tombol.

## 2.5 Explicit State, Never Color-Only State

Setiap status wajib memakai minimal:

- icon;
- text label;
- warna semantik sebagai penguat.

Contoh:

`[ShieldCheck] Aman`

bukan hanya sebuah titik hijau.

## 2.6 Derived State Looks Derived

`Ready for Procurement`, Coverage, Safe Supply, At-Risk Supply, Shortfall, dan Volume Ready tidak boleh tampak seperti field yang bisa diedit.

Tampilan harus menggunakan:

- summary card;
- calculation block;
- status panel;

bukan input, checkbox, switch, atau toggle.

## 2.7 Auditability Is Visible

Pada record yang memengaruhi business state, tampilkan secara konsisten:

- siapa membuat;
- siapa menyetujui;
- terakhir diperbarui;
- alasan perubahan risiko bila ada;
- link `Lihat Riwayat` jika relevan.

## 2.8 Desktop Operational Density

MVP ditujukan untuk laptop lokal. UI boleh lebih padat daripada consumer app, tetapi tidak boleh menjadi spreadsheet penuh yang sulit dibaca.

Target utama:

- 1366×768;
- 1440×900;
- 1920×1080 tetap nyaman.

---

# 3. Brand Foundation

## 3.1 Brand Character

SiagaPasok harus terasa:

- sigap;
- terkoordinasi;
- dapat dipercaya;
- profesional;
- jelas;
- lokal tetapi institusional;
- modern tanpa futurisme berlebihan.

SiagaPasok **tidak** boleh terasa seperti:

- crypto/trading dashboard;
- fintech lending;
- marketplace e-commerce;
- aplikasi monitoring militer;
- dashboard penuh neon/gradient;
- software pemerintah lama yang padat tabel tanpa hierarchy.

## 3.2 Wordmark Direction

Untuk MVP, gunakan wordmark sederhana:

**SiagaPasok**

Aturan:

- `SiagaPasok` ditulis satu kata dengan huruf `S` dan `P` kapital.
- Hindari menulis `SIAGA PASOK` sebagai nama brand utama kecuali pada heading dokumen formal.
- Wordmark dapat memakai icon kecil abstrak berbasis **node + connection** bila nanti dibutuhkan, tetapi icon tersebut tidak menjadi prasyarat implementasi MVP.
- Jangan menggunakan icon daun sebagai logo utama karena produk bukan aplikasi agronomi.
- Jangan menggunakan icon uang, cart, atau shopping bag karena sistem bukan marketplace/fintech.

## 3.3 Tagline

Tagline yang dipertahankan:

> **Pasokan Lokal Siap Sebelum Pengadaan.**

Tagline digunakan di:

- login screen;
- demo intro;
- optional about/product identity area.

Tagline tidak perlu diulang pada setiap halaman internal.

---

# 4. Color System

## 4.1 Brand Colors

| Token | Nama | Hex | Penggunaan |
|---|---|---:|---|
| `brand-navy-950` | Deep Navy | `#0B1F35` | Sidebar dark, strong institutional anchor |
| `brand-navy-900` | Navy | `#12304F` | Sidebar hover/selected support, dark text accent |
| `brand-blue-700` | Cobalt Dark | `#1D4ED8` | Primary hover, strong links |
| `brand-blue-600` | Cobalt | `#2563EB` | Primary action, focus/active accent |
| `brand-blue-100` | Cobalt Soft | `#DBEAFE` | Selected/nav soft backgrounds |
| `brand-blue-50` | Cobalt Mist | `#EFF6FF` | Informational background |

**Primary interactive color:** `#2563EB`.

**Primary dark anchor/sidebar:** `#0B1F35`.

## 4.2 Neutral Colors

| Token | Hex | Penggunaan |
|---|---:|---|
| `neutral-950` | `#0F172A` | Primary text |
| `neutral-800` | `#1E293B` | Strong secondary text |
| `neutral-700` | `#334155` | Secondary text |
| `neutral-500` | `#64748B` | Muted/helper text |
| `neutral-300` | `#CBD5E1` | Strong border / disabled |
| `neutral-200` | `#E2E8F0` | Default border/divider |
| `neutral-100` | `#F1F5F9` | Muted surface |
| `neutral-50` | `#F8FAFC` | Application canvas |
| `white` | `#FFFFFF` | Card/surface |

## 4.3 Supply Semantic Colors

Warna berikut **bukan brand color**. Penggunaannya dibatasi pada status domain.

| State | Label UI | Foreground | Background | Border | Icon |
|---|---|---:|---:|---:|---|
| GREEN | `Aman` | `#166534` | `#F0FDF4` | `#BBF7D0` | `ShieldCheck` |
| YELLOW | `Berisiko` | `#92400E` | `#FFFBEB` | `#FDE68A` | `TriangleAlert` |
| RED | `Kritis` | `#991B1B` | `#FEF2F2` | `#FECACA` | `CircleAlert` |

### Rule

- GREEN hanya untuk **Supply Confidence = GREEN** dan pesan keberhasilan yang benar-benar aman.
- YELLOW/Amber hanya untuk risk/attention yang benar-benar membutuhkan kewaspadaan.
- RED hanya untuk failure, invalid state, destructive action, atau critical supply state.
- Jangan menjadikan warna semantic sebagai warna hero, sidebar, logo, atau primary button.

## 4.4 Workflow & Approval Colors

Approval state harus dibedakan dari supply confidence.

| State | Visual |
|---|---|
| Draft | neutral badge |
| Menunggu Persetujuan | blue/violet-soft badge + `Clock3` |
| Disetujui | brand-blue soft badge + `CircleCheck` |
| Ditolak | red-outline badge + `CircleX` |
| Dibatalkan | neutral-outline badge |
| Kedaluwarsa | neutral-muted badge + `ClockAlert` |

`Disetujui` **tidak otomatis hijau**, karena approval bukan supply confidence.

## 4.5 Ready for Procurement Color

`READY FOR PROCUREMENT` menggunakan **brand blue**, bukan Green.

Alasan:

- Green sudah berarti Confidence Aman.
- RFP adalah hasil tiga gate, bukan sekadar status supply.

Visual:

- TRUE: brand-blue surface + `BadgeCheck` + label `Siap Pengadaan`.
- FALSE: neutral surface + status `Belum Siap` dan daftar reason/gate yang belum terpenuhi.
- Jika FALSE karena shortfall kritis, reason-specific component boleh memakai red/amber, tetapi container RFP tetap bukan red penuh.

---

# 5. Typography

## 5.1 Font Family

**Primary:** Inter Variable.

Fallback:

`Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`

Implementation direction pada Dokumen 5 harus menghindari ketergantungan runtime pada Google Fonts. Jika Inter digunakan, font sebaiknya tersedia melalui package/local build agar demo laptop tidak gagal ketika internet tidak tersedia.

## 5.2 Type Scale

| Token | Size / Line Height | Weight | Use |
|---|---|---:|---|
| `display-sm` | 28 / 36 | 650–700 | Login/product title, demo intro only |
| `page-title` | 24 / 32 | 650 | Judul halaman |
| `section-title` | 18 / 28 | 600 | Judul section/card besar |
| `subsection` | 16 / 24 | 600 | Heading internal |
| `body` | 14 / 22 | 400 | Default app text |
| `body-medium` | 14 / 22 | 500 | Labels/important body |
| `table` | 13 / 20 | 400–500 | Dense data table |
| `caption` | 12 / 18 | 400–500 | Helper/meta text |
| `metric-lg` | 30 / 36 | 650 | KPI utama |
| `metric-md` | 22 / 28 | 650 | KPI compact |

## 5.3 Typography Rules

- Jangan menggunakan uppercase untuk paragraf atau navigation label.
- Uppercase diperbolehkan untuk compact environment badges seperti `DEMO` atau `SIMULASI`.
- Label form memakai sentence case.
- Metric number harus lebih dominan daripada unit.
- `kg`, `%`, tanggal, dan periode harus dipisahkan secara visual agar angka mudah dipindai.

Contoh:

**320 kg**

bukan

**320KG**.

---

# 6. Spacing, Radius, Border, Elevation

## 6.1 Spacing Scale

Gunakan basis 4 px.

Recommended practical scale:

`4, 8, 12, 16, 20, 24, 32, 40, 48`.

## 6.2 Page Spacing

- Desktop page padding: `24px`.
- Compact desktop: `20px`.
- Mobile/tablet fallback: `16px`.
- Gap antar section utama: `24px`.
- Gap card internal: `16px`.

## 6.3 Radius

| Element | Radius |
|---|---:|
| Button / input | 8 px |
| Badge | 999 px hanya untuk status/chip |
| Card | 12 px |
| Dialog / Sheet | 12 px |
| Table container | 10–12 px |

Hindari semua container berbentuk pill.

## 6.4 Border

- Default border: `1px solid neutral-200`.
- Strong/selected border: `neutral-300` atau brand blue sesuai context.
- Card hierarchy mengandalkan border dan whitespace, bukan shadow besar.

## 6.5 Elevation

Shadow hanya untuk:

- Dialog;
- Dropdown Menu;
- Popover;
- Sheet/Drawer;
- floating notification panel.

Dashboard cards secara default **tanpa heavy shadow**.

---

# 7. Layout System

## 7.1 Global App Shell

Desktop app shell:

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│ Sidebar 248px │ Topbar / Context / User                                    │
│               ├─────────────────────────────────────────────────────────────┤
│               │ Breadcrumb                                                  │
│               │ Page title + description + contextual actions               │
│               │                                                             │
│               │ Main content                                                │
│               │                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Sidebar

- Width expanded: 248 px.
- Optional collapsed: 72 px.
- Background: Deep Navy.
- Wordmark di bagian atas.
- Navigation grouped berdasarkan tugas, bukan struktur database.
- Bottom area: organization, role, user menu.

### Topbar

- Height: 64 px.
- White/surface.
- Border bottom subtle.
- Menampilkan context yang tidak cukup di sidebar:
  - environment badge `DEMO` bila aktif;
  - notification bell;
  - user menu.

## 7.2 Main Content Width

- Dashboard: fluid hingga sekitar 1600 px.
- Form/detail: ideal reading width 900–1100 px.
- Approval review: two-column hingga 1400 px bila context comparison diperlukan.

## 7.3 Grid

Gunakan 12-column conceptual grid.

Responsive practical behavior:

- `>=1280`: full desktop grid.
- `1024–1279`: condensed sidebar/metric wrapping.
- `<1024`: sidebar menjadi Sheet/Drawer; table horizontal scroll diperbolehkan.
- `<768`: support minimum functional view, tetapi bukan primary optimization target MVP.

---

# 8. Navigation Information Architecture

## 8.1 System Admin Navigation

1. Dashboard
2. Organisasi
3. Pengguna
4. Audit Administratif

System Admin **tidak** melihat menu Forecast, Produsen, Commitment, Fallback, atau Readiness.

## 8.2 SPPG Navigation

1. Dashboard
2. Forecast Kebutuhan
3. Kesiapan Pasokan
4. Fulfilment Feedback
5. Notifikasi

SPPG tidak mendapat menu Producer Registry atau internal KDKMP supply detail.

## 8.3 KDKMP Operator / FRPL Navigation

1. Dashboard
2. Forecast Masuk
3. Produsen
4. Ekspektasi Panen
5. Komitmen Pasokan
6. Fallback Pasokan
7. Kesiapan
8. Notifikasi

## 8.4 KDKMP Manager Navigation

1. Dashboard
2. Approval Queue
3. Forecast & Pasokan
4. Fallback Pasokan
5. Kesiapan
6. Audit Organisasi
7. Notifikasi

## 8.5 Navigation State Rules

- Active route menggunakan blue-soft background atau inverted sidebar state.
- Badge count hanya dipakai untuk actionable queue, bukan dekorasi.
- Contoh badge: `Approval Queue 4`.
- Jangan menampilkan `0` badge.

---

# 9. Page Header Pattern

Setiap halaman utama memakai struktur:

```text
Breadcrumb
Page Title                     [Primary Action]
1-line description             [Secondary Action optional]
Context metadata / filters
```

Contoh:

```text
Forecast Kebutuhan / F-2026-008

Kangkung — 15 Agustus 2026                         [Revisi Forecast]
Target 375 kg • SPPG Badung 01
```

Rules:

- Maksimum satu primary CTA di header.
- Destructive action ditempatkan di secondary menu atau explicit danger zone.
- Jangan menempatkan `Approve` dan `Reject` sebagai icon-only action.

---

# 10. Core Component Policy — shadcn/ui

## 10.1 Allowed Primary Components

Gunakan shadcn/ui sebagai fondasi:

- Button
- Badge
- Card
- Alert
- AlertDialog
- Dialog
- Sheet
- Drawer jika diperlukan
- Table
- Tabs
- Form
- Input
- Textarea
- Select
- Checkbox
- Radio Group
- Calendar / Popover untuk tanggal jika dibutuhkan
- Breadcrumb
- Dropdown Menu
- Tooltip
- Separator
- Progress
- Skeleton
- Scroll Area
- Command untuk pilihan searchable bila dataset memang membutuhkannya

## 10.2 Component Libraries yang Tidak Digunakan

Jangan menggunakan component library lain seperti:

- Material UI;
- Ant Design;
- Chakra UI;
- Bootstrap component;
- Mantine;
- PrimeReact;
- Flowbite;
- DaisyUI.

## 10.3 Custom Components yang Diperbolehkan

Custom component harus dibangun dari primitives/tokens di atas dan hanya jika merepresentasikan domain SiagaPasok.

Core custom patterns:

- `MetricCard`
- `SupplyStatusBadge`
- `ApprovalStatusBadge`
- `ReadinessGate`
- `CoverageBar`
- `ShortfallAlert`
- `ActionQueueCard`
- `AuditTimeline`
- `ContributorReadinessMatrix`
- `FallbackOfferCard`
- `DemoEnvironmentBanner`

Custom component tidak boleh menggandakan button/input/table yang sudah tersedia di shadcn.

---

# 11. Button System

## 11.1 Variants

| Variant | Use |
|---|---|
| Primary | Satu tindakan utama halaman/form |
| Secondary | Tindakan penting kedua |
| Outline | Utility/filter/non-primary action |
| Ghost | Row action / low emphasis |
| Destructive | Cancel/delete/reject ketika memang destructive |

## 11.2 Button Copy

Gunakan verb yang jelas.

Benar:

- `Simpan Draft`
- `Kirim untuk Persetujuan`
- `Setujui Komitmen`
- `Tolak Komitmen`
- `Siapkan Fallback Request`
- `Terima Penawaran`
- `Perbarui Kesiapan`

Hindari:

- `Submit`
- `OK`
- `Yes`
- `Process`
- `Action`

## 11.3 No Toggle for Business Decisions

Jangan memakai Switch untuk:

- Ready for Procurement;
- Approve/Reject;
- Supply Confidence;
- Accept Fallback.

Business-state transition harus menggunakan explicit action + confirmation bila high impact.

---

# 12. Form System

## 12.1 Form Structure

Form panjang dibagi menjadi section card:

1. Informasi Utama
2. Volume & Periode
3. Sumber / Context
4. Catatan
5. Approval metadata bila read-only review

## 12.2 Label Pattern

```text
Label field *
Helper text singkat jika benar-benar diperlukan
[Input]
Validation message
```

Jangan menggunakan placeholder sebagai pengganti label.

## 12.3 Units

Field volume selalu menampilkan unit dengan jelas.

Contoh:

`Minimum Komitmen [ 80 ] kg`

`Maksimum Komitmen [ 100 ] kg`

## 12.4 Range Input

Range commitment ditampilkan sebagai dua input berdampingan pada desktop:

```text
Minimum (kg)      Maksimum (kg)
[ 80 ]            [ 100 ]
```

Di bawahnya tampil preview:

`Komitmen: 80–100 kg`

Validation:

- minimum > 0;
- maximum >= minimum;
- no implicit decimals jika unit tidak mengizinkan;
- error tampil di field, bukan toast saja.

## 12.5 Dirty / Unsaved State

Form yang belum disimpan dapat memperingatkan ketika pengguna meninggalkan halaman.

## 12.6 Submit State

Saat submit:

- button disabled;
- loading label: `Mengirim...`;
- double-click tidak menimbulkan duplicate record;
- keberhasilan menampilkan confirmation toast + state baru pada halaman.

---

# 13. Approval UX Pattern

Approval merupakan pola kritis karena maker-checker adalah bagian core governance.

## 13.1 Operator View

Operator melihat:

- status record;
- payload yang dibuat;
- `created_by`;
- waktu submit;
- current approval state;
- rejection reason bila ada.

Operator **tidak melihat tombol approve**.

## 13.2 Manager Review View

Manager mendapat halaman review read-only.

Layout desktop:

```text
┌──────────────────────────────┬──────────────────────────────┐
│ Payload yang diajukan        │ Context / Dampak Sistem      │
│                              │                              │
│ Producer                     │ Current Safe Supply          │
│ Range                        │ Shortfall                    │
│ Required Date                │ Forecast Target              │
│ Notes                        │ Related risk                 │
└──────────────────────────────┴──────────────────────────────┘

                         [Tolak] [Setujui]
```

Manager tidak mengedit payload di layar review.

## 13.3 Approve Confirmation

High-impact approval menggunakan `AlertDialog` ringkas.

Contoh:

**Setujui komitmen 80–100 kg?**

`Minimum 80 kg akan masuk Pasokan Aman jika confidence menjadi Aman.`

Buttons:

`Batal` / `Setujui Komitmen`

## 13.4 Reject Pattern

Reject wajib menggunakan Dialog dengan textarea reason.

```text
Alasan Penolakan *
[.............................................]

[Batal] [Tolak Komitmen]
```

Reject tidak boleh icon-only.

---

# 14. Status Badge System

## 14.1 Supply Confidence Badge

| Value | UI Label | Icon |
|---|---|---|
| GREEN | Aman | `ShieldCheck` |
| YELLOW | Berisiko | `TriangleAlert` |
| RED | Kritis | `CircleAlert` |

Secondary text dapat menunjukkan reason:

`Berisiko • Data kedaluwarsa`

atau

`Kritis • Gagal panen`

## 14.2 Approval Badge

| State | Label |
|---|---|
| DRAFT | Draft |
| PENDING_APPROVAL | Menunggu Persetujuan |
| APPROVED | Disetujui |
| REJECTED | Ditolak |
| CANCELLED | Dibatalkan |
| EXPIRED | Kedaluwarsa |

## 14.3 Fallback Request Badge

| State | Label |
|---|---|
| DRAFT | Draft |
| PENDING_APPROVAL | Menunggu Persetujuan |
| OPEN | Terbuka |
| FULFILLED | Terpenuhi |
| EXPIRED | Kedaluwarsa |
| CANCELLED | Dibatalkan |

Partial recovery **bukan badge state baru**. Tampilkan progress:

`80 dari 150 kg terpenuhi`

## 14.4 Fallback Offer Badge

| State | Label |
|---|---|
| DRAFT | Draft |
| PENDING_APPROVAL | Menunggu Persetujuan |
| AVAILABLE | Tersedia |
| ACCEPTED | Diterima |
| REJECTED | Ditolak |
| WITHDRAWN | Ditarik |
| EXPIRED | Kedaluwarsa |

---

# 15. Metric Card System

## 15.1 Required Dashboard Metrics

KDKMP command center menggunakan lima metric utama:

1. Demand Target
2. Safe Supply
3. At-Risk Supply
4. Shortfall
5. Coverage

Metric card format:

```text
PASOKAN AMAN
320 kg
Dari commitment Aman yang disetujui
```

## 15.2 Metric Semantics

### Demand Target

- neutral/brand;
- tidak diberi status warna.

### Safe Supply

- value dapat memakai safe green text/icon;
- card tetap white surface.

### At-Risk Supply

- amber semantic.

### Shortfall

- `0 kg`: neutral/positive understated;
- `>0`: red semantic.

### Coverage

- `100%` tidak otomatis membuat RFP TRUE.
- Helper wajib mengingatkan bahwa Logistics dan Documents masih merupakan gate terpisah.

Contoh:

`100% • Volume terpenuhi`

bukan

`100% • Siap Pengadaan`.

---

# 16. Coverage Bar

Coverage bar adalah visual ringkas untuk hubungan demand dengan supply.

Recommended composition:

```text
Target 375 kg

[ SAFE 320 ][ AT-RISK 40 ][ GAP 55 ]

Pasokan Aman 320 kg  •  Berisiko 40 kg  •  Kekurangan 55 kg
```

Rules:

- Safe segment = green.
- At-Risk segment = amber.
- Shortfall segment = red/outlined red.
- Text legend selalu tersedia.
- Accepted fallback yang valid masuk Safe segment; tidak dibuat warna keempat.
- Surplus tidak membuat bar lebih dari lebar 100%; tampilkan `Surplus +5 kg` sebagai metadata terpisah.

---

# 17. Shortfall Alert Pattern

Jika `Shortfall > 0`, tampilkan persistent Alert pada detail Forecast dan dashboard KDKMP requester.

```text
[CircleAlert] Kekurangan Pasokan 55 kg
Pasokan Aman saat ini 320 kg dari target 375 kg.
At-Risk Supply 40 kg tidak dihitung sebagai Pasokan Aman.

[Siapkan Fallback Request]
```

Alert tidak boleh hilang hanya karena user menutup toast.

Jika fallback request sudah OPEN:

CTA berubah menjadi `Lihat Fallback Request`.

---

# 18. Readiness Gate Pattern

Readiness ditampilkan sebagai tiga gate yang eksplisit.

```text
Kesiapan Pengadaan

[✓] Volume        Siap         380 / 375 kg
[✓] Logistik      Disetujui    2/2 contributor
[!] Dokumen       Belum Siap   1 contributor membutuhkan approval

Status Akhir: BELUM SIAP PENGADAAN
```

## 18.1 Gate Rules

### Volume Ready

- icon calculator/check;
- derived;
- tidak dapat diklik sebagai toggle.

### Logistics Ready

- menunjukkan contributor count;
- link ke readiness matrix.

### Document Ready

- menunjukkan contributor count;
- link ke readiness matrix.

### Final RFP

Jika seluruh gate lulus:

```text
[BadgeCheck] SIAP PENGADAAN
Semua contributor memenuhi Volume, Logistik, dan Dokumen.
```

Jika tidak:

```text
[CircleDashed] BELUM SIAP
2 gate masih membutuhkan tindakan.
```

---

# 19. Contributor Readiness Matrix

Untuk multi-supplier forecast, gunakan matrix karena status final bergantung pada seluruh contributor.

| Contributor | Kontribusi Aman | Logistik | Dokumen | Dampak |
|---|---:|---|---|---|
| KDKMP A | 320 kg | Disetujui | Disetujui | Valid |
| KDKMP B | 60 kg fallback | Belum Disetujui | Disetujui | Menghambat RFP |

Rules:

- Contributor yang tidak lagi memiliki effective Safe Supply tidak perlu menghambat RFP.
- SPPG melihat aggregate/readiness, bukan producer detail.
- Row dengan unresolved gate memakai explicit warning label.

---

# 20. Table Design

## 20.1 Density

- Header: 36–40 px.
- Row: 46–52 px.
- Cell padding horizontal: 12–16 px.
- Default font: 13 px.

## 20.2 Table Pattern

Setiap operational table dapat memiliki:

- search bila dataset > ±10 row;
- filter status;
- filter period/commodity jika relevan;
- sort terbatas pada kolom penting;
- pagination hanya jika diperlukan.

MVP 15–20 produsen tidak membutuhkan pagination agresif.

## 20.3 Row Actions

Gunakan kebijakan:

- primary row click → detail;
- `...` menu untuk secondary actions;
- high-impact action tetap explicit di detail page.

## 20.4 No Hidden Critical State

Confidence, approval state, dan last updated tidak boleh tersembunyi di menu `...`.

---

# 21. Dashboard Composition

## 21.1 KDKMP Operator Dashboard — Command Center

Prioritas tertinggi karena Operator adalah aktor yang paling sering melakukan update.

Recommended desktop composition:

```text
Forecast Context: [SPPG] [Komoditas] [Periode]

┌──────────┬──────────┬──────────┬──────────┬──────────┐
│ Demand   │ Safe     │ At-Risk  │ Shortfall│ Coverage │
└──────────┴──────────┴──────────┴──────────┴──────────┘

┌────────────────────────────────────┬──────────────────────────────┐
│ Coverage & Readiness               │ Action Queue                 │
│ segmented bar                      │ 2 commitment stale           │
│ 3 readiness gates                  │ 1 fallback needed            │
│                                    │ 1 readiness revision         │
└────────────────────────────────────┴──────────────────────────────┘

┌────────────────────────────────────┬──────────────────────────────┐
│ Upcoming Commitments               │ Active Fallback              │
│ Table                              │ Requests / Offers            │
└────────────────────────────────────┴──────────────────────────────┘

Alerts / recent activity
```

## 21.2 KDKMP Manager Dashboard

Fokus pada keputusan, bukan data entry.

```text
┌────────────────────────────────────┬──────────────────────────────┐
│ Current Supply Position            │ Approval Queue               │
│ Metrics + Readiness                │ Commitments       3          │
│                                    │ Recovery          1          │
│                                    │ Fallback Offers   2          │
│                                    │ Readiness         1          │
└────────────────────────────────────┴──────────────────────────────┘

Open Shortfalls
Active Fallback Decisions
Recent Risk Changes
```

## 21.3 SPPG Dashboard

SPPG melihat demand-centric aggregate view.

```text
Upcoming Forecasts

┌──────────┬──────────┬──────────┬──────────┐
│ Demand   │ Safe     │ Shortfall│ Coverage │
└──────────┴──────────┴──────────┴──────────┘

Readiness by Forecast
Contributor aggregate matrix
Recent RFP gained/lost
Fulfilment feedback pending
```

SPPG tidak memiliki Producer list widget.

## 21.4 System Admin Dashboard

Minimal:

- active organizations;
- active users;
- accounts needing activation;
- recent administrative audit.

Tidak ada supply metrics.

---

# 22. Forecast Detail Page

Forecast Detail menjadi halaman konteks utama untuk orkestrasi.

Recommended sections:

1. Forecast Header
2. KPI / Coverage Summary
3. Shortfall / Exception Alert
4. Supply Commitment Summary
5. Fallback Status
6. Contributor Readiness
7. Ready for Procurement Panel
8. Activity / Audit Timeline

Tab diperbolehkan jika halaman terlalu panjang:

- Ringkasan
- Pasokan
- Fallback
- Kesiapan
- Riwayat

`Ringkasan` selalu menjadi default.

---

# 23. Producer Registry Page

Columns:

- Nama Produsen
- Lokasi/Desa
- Komoditas Aktif
- Ekspektasi Panen Terdekat
- Terakhir Diperbarui
- Status Aktif

Jangan tampilkan:

- reliability score;
- ranking;
- “top farmer”;
- nilai finansial.

Producer detail boleh memiliki:

- identitas internal;
- lokasi umum;
- commodity associations;
- expected harvest history;
- linked commitment history.

---

# 24. Commitment Page

Columns:

- Produsen
- Forecast
- Komoditas
- Range
- Confidence
- Approval
- Required Date
- Last Updated

Detail page harus memisahkan:

- **Commitment Payload**;
- **Confidence Condition**;
- **Approval History**.

Ini penting agar user memahami bahwa:

`APPROVED` tidak sama dengan `GREEN` selamanya.

---

# 25. Confidence Update Pattern

## 25.1 Downgrade

Operator memilih `Laporkan Risiko`.

Dialog:

```text
Status Baru *
(o) Berisiko
(o) Kritis

Alasan *
[ Cuaca / Hama / Volume turun / Jadwal bergeser / Logistik / Lainnya ]

Catatan Kondisi *
[................................................]

[Batal] [Simpan Perubahan Risiko]
```

Warning:

`Perubahan akan langsung memengaruhi Pasokan Aman dan dapat memunculkan Shortfall.`

## 25.2 Recovery

YELLOW page menampilkan CTA:

`Ajukan Pemulihan ke Aman`

Operator mengisi reason/evidence note → status recovery menunggu Manager.

Tidak ada tombol `Ubah ke Hijau` langsung.

## 25.3 RED

RED terminal ditampilkan dengan note:

`Status Kritis bersifat terminal untuk commitment ini. Jika pasokan baru tersedia, buat commitment baru.`

---

# 26. Fallback Request UX

## 26.1 Requester View

Pada Shortfall:

`Siapkan Fallback Request`.

Form hanya menampilkan data broadcast-safe:

- komoditas;
- volume kebutuhan;
- required period/date;
- response deadline;
- note agregat.

Sebelum submit tampilkan preview:

> Informasi berikut akan terlihat oleh KDKMP jaringan. Data produsen internal tidak akan dibagikan.

## 26.2 Request Progress

Partial recovery ditampilkan sebagai progress, bukan state badge baru.

```text
Fallback Request
80 / 150 kg terpenuhi
████████░░░░░
Sisa 70 kg
```

## 26.3 Broadcast Privacy

KDKMP supplier hanya melihat:

- requester organization;
- general location;
- commodity;
- requested/remaining volume;
- required date;
- response deadline.

Jangan tampilkan expandable “source details” requester.

---

# 27. Fallback Offer UX

## 27.1 Supplier Side

Saat membuat Offer, UI menampilkan:

```text
Eligible Safe Capacity
120 kg

Sudah Reserved
20 kg

Tersedia untuk Offer
100 kg
```

Input:

`Volume Ditawarkan`.

Helper:

`Volume akan direservasi setelah Manager menyetujui penawaran.`

## 27.2 Supplier Manager Review

Review harus menampilkan **capacity context** supaya Manager tidak sekadar menyetujui angka tanpa melihat dampaknya.

## 27.3 Requester Manager Acceptance

Card:

```text
KDKMP B
Tersedia: 60 kg
Dibutuhkan saat ini: 55 kg
Kedaluwarsa: 10 Agustus 2026 • 18:00

[Reject] [Terima Penawaran]
```

Jika partial acceptance diperbolehkan, dialog meminta volume accepted dan membatasi maksimum berdasarkan rule.

## 27.4 Accepted ≠ Permanently Safe

Setelah Accepted, label:

`Diterima • Underlying Supply Aman`

Jika source degrade:

`Diterima • Underlying Supply Berisiko`

Hal ini mencegah user menyamakan accepted allocation dengan kepastian fisik permanen.

---

# 28. Logistics Readiness UX

Checklist hanya memeriksa kesiapan, bukan physical QC.

Suggested controlled-demo requirements:

- Jadwal pickup/delivery terkonfirmasi
- Kendaraan tersedia
- PIC logistik tersedia
- Wadah/packaging tersedia
- Waktu pengiriman memungkinkan

Requirement actual harus configurable.

Pattern:

```text
Kesiapan Logistik — Forecast F-008

[x] Jadwal pickup terkonfirmasi
[x] Kendaraan tersedia
[x] PIC logistik tersedia
[x] Packaging tersedia
[ ] Waktu pengiriman telah dikonfirmasi

Catatan
[........................................]

[Simpan Draft] [Kirim untuk Persetujuan]
```

Manager review read-only.

Jika approved kemudian Operator mengubah satu item:

- approval langsung invalid;
- tampil banner `Kesiapan perlu persetujuan ulang`.

---

# 29. Document Readiness UX

Document Readiness harus membedakan:

- organization-level document;
- forecast-specific requirement.

UI dapat mengelompokkan:

```text
Dokumen Organisasi
[✓] Dokumen legal organisasi — Valid
[✓] Dokumen administrasi — Valid

Dokumen Periode / Forecast
[!] Requirement A — Belum tersedia
[✓] Requirement B — Tersedia
```

Jangan hard-code label “SLHS Petani” atau requirement yang sudah dinyatakan di luar scope.

Document expiry tampil jelas:

`Berlaku sampai 31 Des 2026`.

---

# 30. Notification Design

## 30.1 Notification Types

Primary in-app notifications:

1. Commitment perlu approval
2. Confidence downgrade / risk update
3. Stale commitment
4. Shortfall muncul/membesar
5. Fallback request masuk
6. Fallback offer tersedia / perlu keputusan
7. Readiness perlu approval / invalidated
8. RFP tercapai atau hilang

## 30.2 Priority Labels

- `Tindakan` — requires user action.
- `Peringatan` — risk/shortfall/invalidation.
- `Informasi` — state change yang tidak memerlukan action langsung.

## 30.3 Notification Center

Setiap notification card:

- icon;
- title;
- context;
- relative timestamp;
- CTA;
- read/unread state.

Critical information tidak boleh disampaikan hanya lewat toast.

## 30.4 Toast Policy

Toast hanya untuk feedback transient:

- Draft tersimpan.
- Komitmen dikirim untuk persetujuan.
- Penawaran berhasil diterima.

Toast tidak digunakan sebagai satu-satunya tampilan:

- shortfall;
- RFP lost;
- rejected approval;
- critical supply risk.

---

# 31. Audit Trail UI

Audit Trail menggunakan vertical timeline sederhana.

Contoh:

```text
09 Agu 2026 • 14:22
Rina — Operator KDKMP A
Mengubah confidence: Aman → Berisiko
Alasan: estimasi volume turun setelah hujan deras

09 Agu 2026 • 13:10
Budi — Manager KDKMP A
Menyetujui Commitment v1 • 80–100 kg
```

Rules:

- data before/after dapat dibuka melalui `Lihat Detail Perubahan`.
- JSON mentah tidak ditampilkan pada user biasa.
- System event diberi actor `Sistem`.

---

# 32. Empty, Loading, Error, and Conflict States

## 32.1 Empty State

Empty state harus menjelaskan next action.

Contoh:

**Belum ada komitmen pasokan**  
Buat commitment dari Expected Harvest yang relevan untuk mulai membangun Pasokan Aman.

`[Buat Komitmen]`

## 32.2 Loading

- Skeleton untuk dashboard/card/table.
- Jangan menampilkan angka `0` sementara data masih loading karena dapat disalahartikan sebagai Shortfall riil.

## 32.3 Error

Error harus menjelaskan apakah:

- input salah;
- permission ditolak;
- state sudah berubah;
- network/server gagal.

## 32.4 State Conflict

Race-condition/conflict message harus spesifik.

Contoh:

**Penawaran tidak dapat disetujui**  
Kapasitas tersedia telah berubah sejak halaman ini dibuka. Muat ulang data sebelum melanjutkan.

CTA:

`Muat Ulang Data`

## 32.5 Stale UI Protection

Jika action gagal karena record sudah berubah di server, jangan silently retry high-impact business action.

---

# 33. Destructive Action Pattern

Actions seperti:

- Cancel Forecast;
- Cancel Fallback Request;
- Withdraw Offer;
- Deactivate Producer/User;

menggunakan `AlertDialog` dengan:

- consequence text;
- reason jika business state membutuhkan;
- explicit destructive button.

Jangan menggunakan browser native `confirm()`.

---

# 34. Search, Filter, and Context Controls

## 34.1 Forecast Context Selector

KDKMP dashboard perlu context yang jelas:

- SPPG;
- commodity;
- required period.

Jika hanya ada satu active SPPG di demo, jangan menampilkan selector yang tidak perlu.

## 34.2 Filters

Common filters:

- Status
- Commodity
- Required period
- Approval state
- Confidence

Filter chips harus dapat di-reset.

## 34.3 Search

Search digunakan terutama untuk:

- producer name;
- organization;
- forecast code.

Jangan menambahkan global omnibox bila MVP belum membutuhkannya.

---

# 35. Date, Time, and Number Formatting

## 35.1 Language

UI menggunakan locale Indonesia.

Recommended display:

- `9 Agustus 2026`
- `10 Agu 2026 • 18.00`
- `15–21 Agustus 2026`

## 35.2 Volume

- `375 kg`
- `80–100 kg`
- decimal jika memang diperlukan: `12,5 kg`.

## 35.3 Percentage

- default whole number: `85%`.
- gunakan satu desimal hanya jika business value benar-benar memerlukan.

## 35.4 Relative Time

Notification dapat memakai:

- `5 menit lalu`
- `2 jam lalu`

Detail/audit tetap menampilkan timestamp absolut.

---

# 36. Language & Microcopy

## 36.1 Primary Domain Labels

| Concept | Label Utama UI | Secondary/Tooltip |
|---|---|---|
| Demand Forecast | Forecast Kebutuhan | Demand Forecast |
| Expected Harvest | Ekspektasi Panen | Expected Harvest |
| Supply Commitment | Komitmen Pasokan | Supply Commitment |
| Safe Supply | Pasokan Aman | Safe Supply |
| At-Risk Supply | Pasokan Berisiko | At-Risk Supply |
| Shortfall | Kekurangan Pasokan | Shortfall |
| Supply Confidence | Confidence Pasokan | Supply Confidence |
| Fallback Request | Permintaan Fallback | Local Supply Recovery Request |
| Fallback Offer | Penawaran Fallback | Fallback Offer |
| Logistics Readiness | Kesiapan Logistik | Logistics Readiness |
| Document Readiness | Kesiapan Dokumen | Document Readiness |
| Ready for Procurement | Siap Pengadaan | Ready for Procurement |
| Fulfilment Feedback | Umpan Balik Pemenuhan | Fulfilment Feedback |

## 36.2 Tone

Microcopy harus:

- langsung;
- operasional;
- tidak menghakimi produsen;
- tidak dramatik;
- tidak membuat kepastian palsu.

Benar:

`Pasokan Aman turun 55 kg setelah satu commitment menjadi Berisiko.`

Hindari:

`Krisis! Petani gagal memenuhi kewajiban.`

## 36.3 No Farmer Scoring Language

Dilarang menggunakan:

- Petani Terbaik
- Reliability Score
- Rating Produsen
- Produsen Buruk
- High Risk Farmer

Risk selalu melekat pada **commitment**, bukan identitas permanen produsen.

---

# 37. Accessibility

## 37.1 Color Contrast

Target minimal WCAG AA untuk text/interface.

## 37.2 Focus State

Semua interactive control memiliki visible focus ring menggunakan brand blue.

## 37.3 Keyboard

Form, dialog, menu, table actions, dan approval harus dapat digunakan dengan keyboard.

## 37.4 Icon Usage

Icon tidak berdiri sendiri untuk aksi penting. Icon-only button hanya untuk utility yang sangat umum dan harus memiliki tooltip/accessible label.

## 37.5 Status Redundancy

Status selalu menggunakan:

`icon + text + color`.

## 37.6 Touch/Click Target

Primary control target minimal sekitar 40–44 px height.

---

# 38. Responsive Behavior

MVP laptop-first, tetapi desain tidak boleh runtuh pada viewport lebih kecil.

## 38.1 Desktop >=1280

- sidebar 248;
- metric 5-column jika cukup;
- two-column content panels.

## 38.2 Compact Desktop 1024–1279

- metric cards wrap 3 + 2;
- sidebar dapat collapsed;
- two-column panel dapat menjadi 60/40.

## 38.3 Tablet 768–1023

- sidebar menjadi Sheet;
- cards 2-column;
- tables horizontal scroll;
- approval review stack vertical.

## 38.4 Mobile <768

Functional minimum:

- cards single column;
- table may transform to stacked rows only if implementation cost reasonable;
- no requirement untuk perfect mobile parity pada MVP.

---

# 39. Demo Mode Design

Demo utility wajib terpisah secara visual dari production-like business workflow.

## 39.1 Environment Banner

Ketika demo utilities aktif:

```text
DEMO • SIMULASI TERKENDALI
Data pada lingkungan ini digunakan untuk demonstrasi dan bukan data operasional nyata.
```

Banner tipis muncul di topbar/header dan tidak memakai warna semantic supply.

Recommended: violet/indigo-soft atau neutral dark.

## 39.2 Role Switch

Jika local demo membutuhkan Role Switch:

- ditempatkan pada `Demo Controls` khusus;
- tidak berada dalam account menu production biasa;
- diberi label jelas `Demo Role Switch`;
- hidden/disabled pada production architecture.

## 39.3 Scenario Controls

Controlled scenario stages:

- Normal
- Gangguan Pasokan
- Shortfall
- Fallback
- Pulih

Scenario control **bukan fitur bisnis**.

Tampilkan dalam panel terpisah:

`Demo Controls — Simulasi Juri`

Jangan membuat button “Cuaca Buruk” di halaman production KDKMP karena dapat memberi kesan bahwa user secara normal mensimulasikan cuaca dari dashboard.

## 39.4 Demo Data Labels

Seed value seperti demand 375 kg harus memiliki marker kecil bila tampil dalam konteks demo intro/data source:

`Data simulasi`.

Tidak perlu menempelkan label pada setiap cell jika seluruh environment sudah memiliki persistent demo banner.

---

# 40. Iconography

Gunakan Lucide Icons secara konsisten.

Recommended semantic mapping:

| Concept | Icon Direction |
|---|---|
| Dashboard | `LayoutDashboard` |
| Forecast | `ClipboardList` / `CalendarRange` |
| Producer | `Users` |
| Expected Harvest | `Sprout` |
| Commitment | `Handshake` atau `FileCheck` |
| Safe | `ShieldCheck` |
| At Risk | `TriangleAlert` |
| Critical | `CircleAlert` |
| Fallback | `Network` / `RefreshCcw` |
| Logistics | `Truck` |
| Documents | `Files` / `FileCheck2` |
| Approval | `CircleCheckBig` |
| Pending | `Clock3` |
| Notification | `Bell` |
| Audit | `History` |
| Ready for Procurement | `BadgeCheck` |

Hindari campuran icon library.

---

# 41. Visualisation Policy

## 41.1 MVP Visualisations yang Diizinkan

- Metric cards
- Progress bar
- Segmented coverage bar
- Status matrix
- Timeline
- Small trend indicator jika memiliki data historis yang nyata

## 41.2 Visualisations yang Ditunda

- pie chart;
- donut chart;
- complex line chart;
- Sankey;
- geographic WebGIS;
- predictive chart;
- farmer ranking chart.

Alasan: orchestration flow lebih penting daripada analytics pada MVP.

## 41.3 No Decorative Charts

Chart tidak boleh dibuat hanya untuk membuat dashboard “terlihat canggih”.

---

# 42. Key Screen Blueprints

## 42.1 Login

```text
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│                        SiagaPasok                           │
│              Pasokan Lokal Siap Sebelum Pengadaan.         │
│                                                             │
│                   Email                                    │
│                   [____________________]                    │
│                   Password                                 │
│                   [____________________]                    │
│                                                             │
│                   [ Masuk ]                                 │
│                                                             │
│        Closed system • Akun dibuat oleh administrator       │
└─────────────────────────────────────────────────────────────┘
```

No public registration link.

## 42.2 Operator Dashboard

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ Dashboard KDKMP                                                          │
│ Forecast aktif: Kangkung • 15 Agu 2026                                   │
├───────────┬───────────┬───────────┬───────────┬──────────────────────────┤
│ Demand    │ Safe      │ At-Risk   │ Shortfall │ Coverage                 │
│ 375 kg    │ 320 kg    │ 40 kg     │ 55 kg     │ 85%                      │
├───────────────────────────────────────────┬──────────────────────────────┤
│ Coverage + Readiness                      │ Tindakan Dibutuhkan          │
│ [safe][risk][gap]                         │ • 1 shortfall                │
│ Volume: Belum                             │ • 2 stale commitment         │
│ Logistics: Approved                       │ • 1 approval readiness       │
│ Documents: Approved                       │                              │
├───────────────────────────────────────────┴──────────────────────────────┤
│ Active Commitments / Fallback / Alerts                                   │
└──────────────────────────────────────────────────────────────────────────┘
```

## 42.3 Manager Approval Queue

```text
Approval Queue

[Commitment 3] [Recovery 1] [Fallback 2] [Readiness 1]

Type        Record              Maker        Submitted        Status
Commitment  Kangkung 80–100 kg  Rina OP      14:10            Pending
Fallback    Offer 60 kg         Dedi OP      14:05            Pending
```

## 42.4 SPPG Forecast Detail

```text
Forecast F-008 • Kangkung • 15 Agu 2026

Demand 375 kg | Safe 380 kg | Shortfall 0 | Coverage 101%
Surplus 5 kg

Readiness
Volume      ✓
Logistics   ! 1 contributor pending
Documents   ✓

BELUM SIAP PENGADAAN

Contributor Matrix
KDKMP A 320 kg  Logistics ✓  Docs ✓
KDKMP B  60 kg  Logistics !  Docs ✓
```

---

# 43. Component Behaviour Matrix

| Domain Event | Visual Reaction | Persistent? | CTA |
|---|---|---:|---|
| Commitment approved | Badge update + metric recalc | Yes | View Commitment |
| GREEN→YELLOW | Risk badge + Safe Supply recalc + alert | Yes | Lihat Risiko |
| GREEN/YELLOW→RED | Critical badge + recalc | Yes | Lihat Detail |
| Shortfall > 0 | Shortfall Alert | Yes | Siapkan/Lihat Fallback |
| Offer AVAILABLE | Action notification | Until decided | Terima/Tolak |
| Offer ACCEPTED | Offer badge + recalc | Yes | Lihat Allocation |
| Readiness invalidated | Gate becomes incomplete + alert | Yes | Perbaiki Kesiapan |
| RFP TRUE | Brand-blue RFP panel | Yes | Lihat Forecast |
| RFP FALSE after TRUE | Warning alert + RFP panel update | Yes | Lihat Penyebab |
| Form saved | Toast | No | — |
| Race conflict | Error/Alert | Until acknowledged | Muat Ulang |

---

# 44. Design Tokens Summary

## 44.1 Core Tokens

```text
Canvas        #F8FAFC
Surface       #FFFFFF
Text          #0F172A
Muted Text    #64748B
Border        #E2E8F0
Primary       #2563EB
Primary Hover #1D4ED8
Sidebar       #0B1F35
Safe          #166534
At Risk       #92400E
Critical      #991B1B
```

## 44.2 Density Tokens

```text
App Header      64px
Sidebar         248px
Page Padding    24px
Card Radius     12px
Control Radius   8px
Control Height  40–44px
Table Row       46–52px
```

---

# 45. UI Invariants

Implementasi wajib menjaga aturan berikut.

1. GREEN/YELLOW/RED tidak digunakan sebagai brand primary.
2. Status selalu memiliki text label, bukan warna saja.
3. RFP tidak pernah berupa Switch/Checkbox editable.
4. Coverage 100% tidak pernah otomatis diberi copy “Siap Pengadaan”.
5. Yellow supply tidak divisualisasikan sebagai bagian dari Safe Supply.
6. Expected Harvest tidak pernah diberi visual yang menyerupai committed Safe Supply.
7. `AVAILABLE` Fallback Offer tidak divisualisasikan sebagai supply yang sudah dimiliki requester.
8. Accepted fallback menampilkan kondisi underlying supply bila berubah.
9. Manager review bersifat read-only terhadap payload yang sedang disetujui.
10. Reject high-impact state selalu meminta reason bila business rule mewajibkan.
11. System Admin tidak memiliki navigation menuju operational supply records.
12. SPPG tidak melihat producer-level detail.
13. KDKMP supplier tidak melihat internal producer requester pada fallback broadcast.
14. Shortfall > 0 menghasilkan persistent visual warning.
15. Critical business state tidak hanya disampaikan melalui toast.
16. Loading state tidak menampilkan temporary `0 kg` yang dapat disalahartikan.
17. Approval status dan confidence status ditampilkan sebagai dua konsep berbeda.
18. Partial fallback recovery ditampilkan sebagai progress, bukan state `PARTIALLY_FULFILLED`.
19. Logistics readiness tidak memiliki physical QC/photo grading flow.
20. Document checklist tidak mengklaim satu requirement nasional yang tidak dikunci PRD.
21. Demo controls selalu ditandai sebagai utility simulasi.
22. Role Switch demo tidak menyatu dengan production role/account architecture.
23. Destructive actions menggunakan explicit confirmation.
24. Stale/conflict server response tidak boleh silently overwrite data terbaru.
25. Audit metadata tersedia pada record yang memengaruhi supply truth.
26. Surplus tidak membuat coverage visual bar melampaui 100%; nilai surplus ditampilkan terpisah.
27. Action queue memprioritaskan actionable records, bukan semua aktivitas.
28. No farmer rating/ranking language di seluruh UI.
29. No price/bidding/payment visual pattern pada Fallback.
30. Semua terminology utama mengikuti glossary dokumen ini.

---

# 46. Acceptance Criteria — Design System

Design System dianggap siap diterjemahkan ke implementasi jika seluruh kriteria berikut terpenuhi.

| ID | Acceptance Criterion |
|---|---|
| DS-AC01 | Brand primary bukan Green/Yellow/Red. |
| DS-AC02 | Tersedia palette brand, neutral, semantic supply, dan workflow status yang terpisah. |
| DS-AC03 | Semua supply status memakai icon + text + color. |
| DS-AC04 | Bahasa utama UI adalah Bahasa Indonesia. |
| DS-AC05 | shadcn/ui ditetapkan sebagai primary component system. |
| DS-AC06 | Tidak ada secondary component library. |
| DS-AC07 | App shell dan navigation didefinisikan per role. |
| DS-AC08 | SPPG dan System Admin tidak diberi visual access ke producer-level operations. |
| DS-AC09 | Dashboard KDKMP menampilkan Demand, Safe, At-Risk, Shortfall, Coverage. |
| DS-AC10 | Readiness ditampilkan sebagai tiga gate terpisah. |
| DS-AC11 | RFP derived state tidak menggunakan editable control. |
| DS-AC12 | Approval UX memisahkan Maker dan Checker. |
| DS-AC13 | Manager review read-only dan rejection memiliki reason pattern. |
| DS-AC14 | Confidence downgrade dan recovery mempunyai interaction pattern yang berbeda. |
| DS-AC15 | Shortfall mempunyai persistent actionable alert. |
| DS-AC16 | Fallback broadcast menjaga privacy organization-scoped data. |
| DS-AC17 | Fallback offer UI menampilkan eligible/reserved capacity untuk supplier-side. |
| DS-AC18 | AVAILABLE tidak diperlakukan sebagai accepted supply. |
| DS-AC19 | Partial fallback ditampilkan dengan volume progress. |
| DS-AC20 | Accepted fallback degradation dapat terlihat jelas. |
| DS-AC21 | Logistics dan Document Readiness memiliki prepare→submit→approve UX. |
| DS-AC22 | Revision setelah approval menampilkan invalidation secara jelas. |
| DS-AC23 | Notification rules membedakan persistent state vs transient toast. |
| DS-AC24 | Audit history mempunyai visual timeline/readable changes. |
| DS-AC25 | Empty/loading/error/conflict state telah didefinisikan. |
| DS-AC26 | UI laptop 1366×768 tetap usable tanpa horizontal page overflow. |
| DS-AC27 | Demo Mode terpisah secara visual dari production-like UI. |
| DS-AC28 | Tidak ada marketplace, financing, PO, physical QC, AI, atau farmer scoring visual pattern. |
| DS-AC29 | Typography, spacing, radius, border, dan layout tokens telah didefinisikan. |
| DS-AC30 | Design tokens cukup jelas untuk diturunkan menjadi Tailwind/shadcn theme pada Dokumen 5. |

---

# 47. Deferred Design Decisions

Hal berikut tidak menghalangi implementation planning dan dapat diselesaikan saat build:

1. custom SVG logo final;
2. exact sidebar collapsed behavior;
3. apakah DataTable kompleks membutuhkan TanStack Table;
4. apakah date input memakai Calendar popover atau native-friendly field;
5. subtle motion/transitions;
6. exact charting dependency jika kelak analytics ditambahkan;
7. mobile-specific table-to-card transformation;
8. dark mode — **tidak menjadi requirement MVP**;
9. public marketing landing page — bukan fokus working application;
10. downloadable report/PDF — bukan MVP requirement.

---

# 48. Traceability ke Dokumen Sebelumnya

| Design Decision | Source Foundation |
|---|---|
| Role-specific navigation | PRD §6 + User Flow §3 |
| Demand/Safe/At-Risk/Shortfall/Coverage cards | PRD §9–10 + User Flow §19 |
| No color-only status | Project locked accessibility direction |
| Green/Amber/Red semantic only | Project locked branding rule |
| Maker-checker approval pattern | PRD §6.3 + User Flow §8/14/15 + ERD §14 |
| Confidence downgrade vs recovery UX | User Flow §9 + ERD commitment/confidence model |
| Fallback privacy | PRD §12.1 + User Flow §11–12 + ERD §16 |
| Source-backed offer capacity context | PRD §10.3 + User Flow §12 + ERD §15 |
| Partial request as progress, not state | User Flow §11.3 + ERD-D10 |
| Multi-contributor readiness matrix | PRD §10.4 + User Flow §16 + ERD §12.8–12.12 |
| RFP derived, non-toggle | PRD §10.4 + ERD §2.2 |
| Readiness invalidation visual | User Flow §14–16 + ERD readiness versioning |
| No physical QC | PRD §4 + research corrections |
| Fulfilment feedback | PRD §9 + User Flow §17 + ERD fulfilment entity |
| Demo utility separation | PRD controlled simulation + User Flow §22 |

---

# 49. Exit Criteria untuk Dokumen 5 — Modular Implementation Plan

Design System dianggap cukup matang untuk berlanjut apabila:

- brand palette dan semantic palette sudah tidak ambigu;
- primary component system sudah dikunci ke shadcn/ui;
- role-specific app shell/navigation sudah jelas;
- pola dashboard dan key screen sudah jelas;
- maker-checker UX sudah jelas;
- confidence, fallback, readiness, dan RFP punya representasi yang tidak bertentangan dengan business rules;
- accessibility dan responsive baseline sudah ditentukan;
- demo controls tidak bercampur dengan production features;
- tidak ada keputusan visual yang mengharuskan perubahan PRD/User Flow/ERD.

Jika dokumen ini disetujui, tahap berikutnya adalah:

> **05 — Modular Implementation Plan**
>
> Dokumen tersebut akan menerjemahkan empat foundation documents menjadi urutan pembangunan Laravel + Inertia.js + React + Vite + shadcn/ui, dependency plan, module boundaries, migration/model/service/policy plan, route/page map, seed/demo strategy, testing strategy, dan milestone implementasi — masih sebelum source code mulai ditulis.

---

# 50. Final Design Direction

SiagaPasok harus terlihat seperti **operational coordination system yang dapat dipercaya**, bukan aplikasi showcase yang mengejar visual effects.

Identitas visual final MVP:

> **Deep Navy foundation + Cobalt Blue action system + neutral data surfaces + strict semantic Green/Amber/Red supply states.**

Pengalaman utama yang harus dirasakan pengguna:

> **“Saya tahu kondisi pasokan saat ini, saya tahu risikonya, saya tahu siapa yang harus bertindak, dan saya tahu mengapa sistem belum atau sudah menyatakan Siap Pengadaan.”**

Itulah standar visual dan interaction quality yang harus dipertahankan pada seluruh implementasi SiagaPasok.
