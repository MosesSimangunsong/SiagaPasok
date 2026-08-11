import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    CheckCircle2,
    CircleMinus,
    Clock3,
    FileCheck2,
    Handshake,
    MapPin,
    Send,
    Truck,
} from "lucide-react";
import KdkmpLayout from "@/Layouts/KdkmpLayout";
import { Head, router } from "@inertiajs/react";

export default function Show({
    forecast,
    canCreateCommitment,
    readinessContext,
}) {
    return (
        <>
            <Head title={`${forecast.forecast_code} — SiagaPasok`} />

            <KdkmpLayout
                pageTitle={forecast.forecast_code}
                pageDescription={`${forecast.sppg.name} • ${forecast.commodity.name}`}
                headerActions={
                    <>
                        {canCreateCommitment && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() =>
                                    router.visit(
                                        `/kdkmp/commitments/create?forecast_id=${forecast.id}`,
                                    )
                                }
                            >
                                <Handshake data-icon="inline-start" />
                                Buat Commitment
                            </Button>
                        )}

                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => router.visit("/kdkmp/forecasts")}
                        >
                            <ArrowLeft data-icon="inline-start" />
                            Daftar Forecast
                        </Button>
                    </>
                }
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Status"
                            value={forecast.status_label}
                        />

                        <SummaryCard
                            label="Target Volume"
                            value={formatVolume(forecast)}
                        />

                        <SummaryCard
                            label="Freshness Interval"
                            value={
                                forecast.freshness_interval_hours
                                    ? `${forecast.freshness_interval_hours} jam`
                                    : "Tidak ditentukan"
                            }
                        />
                    </div>
                    <ReadinessOverview
    forecast={forecast}
    context={readinessContext}
/>

                    <Card>
                        <CardHeader className="border-b">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>Detail Kebutuhan SPPG</CardTitle>

                                    <CardDescription>
                                        Informasi Forecast PUBLISHED untuk
                                        perencanaan pasokan KDKMP.
                                    </CardDescription>
                                </div>

                                <span className="inline-flex rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                                    {forecast.status_label}
                                </span>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-7">
                            <section>
                                <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    SPPG
                                </p>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <DetailItem
                                        icon={Building2}
                                        label="Organisasi"
                                        value={forecast.sppg.name}
                                        description={forecast.sppg.code}
                                    />

                                    <DetailItem
                                        icon={MapPin}
                                        label="Lokasi"
                                        value={
                                            forecast.sppg.general_location ||
                                            "Tidak tersedia"
                                        }
                                    />
                                </div>
                            </section>

                            <div className="border-t border-border" />

                            <section>
                                <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Kebutuhan
                                </p>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <DetailItem
                                        label="Komoditas"
                                        value={forecast.commodity.name}
                                        description={forecast.commodity.code}
                                    />

                                    <DetailItem
                                        label="Target Volume"
                                        value={formatVolume(forecast)}
                                    />
                                </div>
                            </section>

                            <div className="border-t border-border" />

                            <section>
                                <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Periode Kebutuhan
                                </p>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <DetailItem
                                        icon={CalendarDays}
                                        label="Mulai Dibutuhkan"
                                        value={formatDateTime(
                                            forecast.required_start_at,
                                        )}
                                    />

                                    <DetailItem
                                        icon={CalendarDays}
                                        label="Batas Akhir"
                                        value={formatDateTime(
                                            forecast.required_end_at,
                                        )}
                                    />
                                </div>
                            </section>

                            <div className="border-t border-border" />

                            <section>
                                <div className="grid gap-5 md:grid-cols-2">
                                    <DetailItem
                                        icon={Clock3}
                                        label="Freshness Interval"
                                        value={
                                            forecast.freshness_interval_hours
                                                ? `${forecast.freshness_interval_hours} jam`
                                                : "Tidak ditentukan"
                                        }
                                    />

                                    <DetailItem
                                        icon={Send}
                                        label="Dipublikasikan"
                                        value={formatDateTime(
                                            forecast.published_at,
                                        )}
                                    />
                                </div>
                            </section>

                            <div className="border-t border-border" />

                            <section>
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Catatan SPPG
                                </p>

                                <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                                    {forecast.notes || "Tidak ada catatan."}
                                </p>
                            </section>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent>
                            <div>
                                <p className="font-medium text-foreground">
                                    Forecast read-only
                                </p>

                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                    Halaman ini tidak menyediakan Edit, Revisi,
                                    Publish, Close, atau Cancel. Lifecycle
                                    Forecast sepenuhnya dikendalikan oleh SPPG.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </KdkmpLayout>
        </>
    );
}

function ReadinessOverview({
    forecast,
    context,
}) {
    if (!context) {
        return null;
    }

    return (
        <Card>
            <CardHeader className="border-b">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <CardTitle>
                            Readiness Contributor
                        </CardTitle>

                        <CardDescription>
                            Logistics dan Document
                            Readiness hanya berlaku
                            ketika KDKMP menjadi
                            contributor efektif pada
                            current Safe Supply.
                        </CardDescription>
                    </div>

                    <ContributorBadge
                        value={
                            context.is_contributor
                        }
                    />
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                {!context.is_contributor && (
                    <div className="rounded-xl border border-border bg-muted/30 p-4">
                        <div className="flex items-start gap-3">
                            <CircleMinus className="mt-0.5 size-5 shrink-0 text-muted-foreground" />

                            <div>
                                <p className="font-medium text-foreground">
                                    Readiness belum
                                    berlaku
                                </p>

                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                    KDKMP ini belum
                                    termasuk contributor
                                    efektif pada current
                                    Safe Supply Forecast.
                                    Status akan berubah
                                    otomatis ketika
                                    kontribusi pasokan
                                    menjadi efektif.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <ReadinessEntry
                        forecast={forecast}
                        type="logistics"
                        label="Logistics Readiness"
                        description="Kesiapan operasional pengiriman dan requirement logistik."
                        icon={Truck}
                        data={
                            context.logistics
                        }
                        applicable={
                            context.is_contributor
                        }
                    />

                    <ReadinessEntry
                        forecast={forecast}
                        type="document"
                        label="Document Readiness"
                        description="Kelengkapan dan validitas evidence dokumen yang dipersyaratkan."
                        icon={FileCheck2}
                        data={
                            context.document
                        }
                        applicable={
                            context.is_contributor
                        }
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function ReadinessEntry({
    forecast,
    type,
    label,
    description,
    icon: Icon,
    data,
    applicable,
}) {
    const prepare = () => {
        router.post(
            `/kdkmp/forecasts/${forecast.id}/readiness/${type}/prepare`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <div className="rounded-xl border border-border p-4">
            <div className="flex items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-4" />
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="font-medium text-foreground">
                            {label}
                        </p>

                        <ReadinessTruthBadge
                            ready={
                                data?.ready
                            }
                            applicable={
                                applicable
                            }
                        />
                    </div>

                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>

                    {data?.status && (
                        <p className="mt-3 text-xs text-muted-foreground">
                            Checklist Version{" "}
                            {data.version_no}
                            {" • "}
                            {approvalLabel(
                                data.status,
                            )}
                        </p>
                    )}

                    {applicable &&
                        !data?.ready &&
                        data?.reason_codes
                            ?.length > 0 && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {data.reason_codes.map(
                                    (reason) => (
                                        <span
                                            key={
                                                reason
                                            }
                                            className="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                        >
                                            {readinessReasonLabel(
                                                reason,
                                            )}
                                        </span>
                                    ),
                                )}
                            </div>
                        )}

                    {(data?.can_prepare ||
                        data?.can_open) && (
                        <div className="mt-4">
                            {data.can_prepare && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={
                                        prepare
                                    }
                                >
                                    <FileCheck2 data-icon="inline-start" />
                                    Siapkan{" "}
                                    {type ===
                                    "logistics"
                                        ? "Logistics"
                                        : "Document"}
                                </Button>
                            )}

                            {data.can_open && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(
                                            `/kdkmp/readiness/${data.checklist_id}`,
                                        )
                                    }
                                >
                                    <FileCheck2 data-icon="inline-start" />
                                    Buka Readiness
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function ContributorBadge({ value }) {
    return (
        <span
            className={[
                "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
                value
                    ? "bg-primary/10 text-primary"
                    : "bg-muted text-muted-foreground",
            ].join(" ")}
        >
            {value ? (
                <CheckCircle2 className="size-3.5" />
            ) : (
                <CircleMinus className="size-3.5" />
            )}

            {value
                ? "Contributor Aktif"
                : "Bukan Contributor"}
        </span>
    );
}

function ReadinessTruthBadge({
    ready,
    applicable,
}) {
    if (!applicable) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                <CircleMinus className="size-3.5" />
                Tidak Berlaku
            </span>
        );
    }

    if (ready) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                <CheckCircle2 className="size-3.5" />
                Ready
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
            <Clock3 className="size-3.5" />
            Belum Ready
        </span>
    );
}

function approvalLabel(value) {
    return (
        {
            DRAFT: "Draft",
            PENDING_APPROVAL:
                "Menunggu Persetujuan",
            APPROVED: "Disetujui",
            REJECTED: "Ditolak",
        }[value] ?? value
    );
}

function readinessReasonLabel(code) {
    const labels = {
        NOT_CURRENT_CONTRIBUTOR:
            "Bukan contributor aktif",
        CHECKLIST_MISSING:
            "Checklist belum disiapkan",
        CHECKLIST_NOT_APPROVED:
            "Belum disetujui Manager",
        FORECAST_VERSION_STALE:
            "Forecast telah direvisi",
        REQUIRED_ITEM_UNSATISFIED:
            "Requirement wajib belum terpenuhi",
        DOCUMENT_INVALID:
            "Dokumen tidak valid",
        FORECAST_WINDOW_ENDED:
            "Periode Forecast berakhir",
    };

    return labels[code] ?? code;
}

function SummaryCard({ label, value }) {
    return (
        <Card>
            <CardContent>
                <p className="text-sm text-muted-foreground">{label}</p>

                <p className="mt-2 text-lg font-semibold text-foreground">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

function DetailItem({ icon: Icon, label, value, description }) {
    return (
        <div className="flex items-start gap-3">
            {Icon && (
                <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-4" />
                </div>
            )}

            <div>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    {label}
                </p>

                <p className="mt-1 font-medium text-foreground">{value}</p>

                {description && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}

function formatVolume(forecast) {
    const number = Number(forecast.target_volume);

    if (!Number.isFinite(number)) {
        return `${forecast.target_volume} ${forecast.unit.symbol}`;
    }

    return `${new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: forecast.unit.decimal_precision ?? 2,
    }).format(number)} ${forecast.unit.symbol}`;
}

function formatDateTime(value) {
    if (!value) {
        return "—";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}
