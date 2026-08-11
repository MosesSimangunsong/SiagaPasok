import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import SppgLayout from "@/Layouts/SppgLayout";
import { Head, router } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    Building2,
    CheckCircle2,
    CircleMinus,
    Clock3,
    FileCheck2,
    PackageCheck,
    Truck,
} from "lucide-react";

export default function Readiness({
    forecast,
    supply,
    procurement,
    contributors,
}) {
    return (
        <>
            <Head
                title={`${forecast.forecast_code} Readiness — SiagaPasok`}
            />

            <SppgLayout
                pageTitle="Readiness Contributor"
                pageDescription={`${forecast.forecast_code} • ${forecast.commodity.name}`}
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                `/sppg/forecasts/${forecast.id}`,
                            )
                        }
                    >
                        <ArrowLeft data-icon="inline-start" />
                        Kembali ke Forecast
                    </Button>
                }
            >
                <div className="space-y-6">
                  <ProcurementStatusPanel
    procurement={procurement}
    contributorCount={
        contributors.length
    }
/>
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Volume Ready"
                            value={
                                supply.volume_ready
                                    ? "Terpenuhi"
                                    : "Belum Terpenuhi"
                            }
                            description={`${formatNumber(
                                supply.total_safe_supply,
                            )} ${forecast.unit.symbol} Safe Supply`}
                            icon={
                                supply.volume_ready
                                    ? CheckCircle2
                                    : Clock3
                            }
                        />

                        <SummaryCard
                            label="Coverage"
                            value={
    supply.coverage_percent ===
    null
        ? "—"
        : `${formatNumber(
              supply.coverage_percent,
              2,
          )}%`
}
                            description={`Target ${formatNumber(
                                forecast.target_volume,
                            )} ${forecast.unit.symbol}`}
                            icon={PackageCheck}
                        />

                        <SummaryCard
                            label="Contributor Efektif"
                            value={
                                contributors.length
                            }
                            description={
                                contributors.length ===
                                1
                                    ? "1 organisasi KDKMP"
                                    : `${contributors.length} organisasi KDKMP`
                            }
                            icon={Building2}
                        />
                    </div>

                    {!supply.volume_ready &&
    supply.shortfall !== null && (
                        <Card className="border-amber-500/30">
                            <CardContent>
                                <div className="flex items-start gap-3">
                                    <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-700" />

                                    <div>
                                        <p className="font-medium text-foreground">
                                            Volume belum
                                            memenuhi kebutuhan
                                        </p>

                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            Current Safe
                                            Supply masih
                                            memiliki shortfall{" "}
                                            {formatNumber(
                                                supply.shortfall,
                                            )}{" "}
                                            {
                                                forecast
                                                    .unit
                                                    .symbol
                                            }
                                            . Readiness
                                            contributor tetap
                                            ditampilkan sebagai
                                            kondisi operasional,
                                            tetapi belum berarti
                                            kebutuhan siap
                                            menuju proses resmi.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Kesiapan per Contributor
                            </CardTitle>

                            <CardDescription>
                                Hanya status aggregate
                                organisasi yang terlihat.
                                Detail producer, commitment,
                                checklist item, dan dokumen
                                internal KDKMP tidak
                                ditampilkan kepada SPPG.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="p-0">
                            {contributors.length ===
                            0 ? (
                                <EmptyContributors />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[900px] text-left text-sm">
                                        <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Contributor
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Logistics
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Document
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Perhatian
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {contributors.map(
                                                (
                                                    contributor,
                                                ) => (
                                                    <ContributorRow
                                                        key={
                                                            contributor
                                                                .organization
                                                                .id
                                                        }
                                                        contributor={
                                                            contributor
                                                        }
                                                    />
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent>
                            <div className="flex items-start gap-3">
                                <CircleMinus className="mt-0.5 size-5 shrink-0 text-muted-foreground" />

                                <div>
                                    <p className="font-medium text-foreground">
                                        Belum ada manual
                                        Ready for Procurement
                                    </p>

                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        Halaman ini hanya
                                        menunjukkan dependency
                                        M08: Volume Ready,
                                        Logistics Readiness,
                                        dan Document Readiness.
                                        Final Ready for
                                        Procurement akan
                                        dihitung secara derived
                                        oleh sistem pada modul
                                        berikutnya, bukan
                                        melalui tombol manual.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </SppgLayout>
        </>
    );
}

function ProcurementStatusPanel({
    procurement,
    contributorCount,
}) {
    const ready =
        procurement.ready_for_procurement;

    const reasonCodes =
        procurement.reason_codes ?? [];

    return (
        <Card
            className={
                ready
                    ? "border-primary/30 bg-primary/5"
                    : "border-amber-500/30"
            }
        >
            <CardContent>
                <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex items-start gap-3">
                        <div
                            className={[
                                "flex size-11 shrink-0 items-center justify-center rounded-xl",
                                ready
                                    ? "bg-primary/10 text-primary"
                                    : "bg-amber-500/10 text-amber-700",
                            ].join(" ")}
                        >
                            {ready ? (
                                <CheckCircle2 className="size-5" />
                            ) : (
                                <AlertTriangle className="size-5" />
                            )}
                        </div>

                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Derived Status
                            </p>

                            <h2 className="mt-1 text-xl font-semibold text-foreground">
                                {ready
                                    ? "READY FOR PROCUREMENT"
                                    : "BELUM READY FOR PROCUREMENT"}
                            </h2>

                            <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                                {ready
                                    ? `Volume telah terpenuhi dan seluruh ${contributorCount} contributor efektif memiliki Logistics dan Document Readiness yang valid. Forecast siap diteruskan ke proses procurement resmi di luar SiagaPasok.`
                                    : "Status ini dihitung otomatis dari current Safe Supply dan readiness seluruh contributor efektif. Tidak ada user yang menetapkan status ini secara manual."}
                            </p>
                        </div>
                    </div>

                    <div className="grid shrink-0 gap-2 sm:grid-cols-3">
                        <DerivedGate
                            label="Volume"
                            ready={
                                procurement.volume_ready
                            }
                        />

                        <DerivedGate
                            label="Logistics"
                            ready={
                                procurement
                                    .all_contributors_logistics_ready
                            }
                        />

                        <DerivedGate
                            label="Document"
                            ready={
                                procurement
                                    .all_contributors_document_ready
                            }
                        />
                    </div>
                </div>

                {reasonCodes.length > 0 && (
                    <div className="mt-5 border-t pt-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Blocker saat ini
                        </p>

                        <div className="mt-2 flex flex-wrap gap-2">
                            {reasonCodes.map(
                                (reason) => (
                                    <span
                                        key={
                                            reason
                                        }
                                        className="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                    >
                                        {procurementReasonLabel(
                                            reason,
                                        )}
                                    </span>
                                ),
                            )}
                        </div>
                    </div>
                )}

                <p className="mt-4 text-xs text-muted-foreground">
                    Dihitung otomatis{" "}
                    {formatDateTime(
                        procurement.evaluated_at,
                    )}
                    . Status dapat kembali menjadi
                    belum ready apabila supply,
                    Forecast, logistics, atau
                    dokumen berubah.
                </p>
            </CardContent>
        </Card>
    );
}

function DerivedGate({
    label,
    ready,
}) {
    return (
        <div className="flex min-w-32 items-center gap-2 rounded-lg border bg-background px-3 py-2">
            {ready ? (
                <CheckCircle2 className="size-4 shrink-0 text-primary" />
            ) : (
                <CircleMinus className="size-4 shrink-0 text-muted-foreground" />
            )}

            <div>
                <p className="text-xs text-muted-foreground">
                    {label}
                </p>

                <p className="text-sm font-medium text-foreground">
                    {ready
                        ? "Ready"
                        : "Belum Ready"}
                </p>
            </div>
        </div>
    );
}

function ContributorRow({
    contributor,
}) {
    const reasons = [
        ...(contributor
            .logistics_reason_codes ?? []),
        ...(contributor
            .document_reason_codes ?? []),
    ];

    const uniqueReasons = [
        ...new Set(reasons),
    ];

    return (
        <tr>
            <td className="px-4 py-4">
                <p className="font-medium text-foreground">
                    {contributor
                        .organization
                        .name ?? "KDKMP"}
                </p>

                {contributor.organization
                    .code && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {
                            contributor
                                .organization
                                .code
                        }
                    </p>
                )}
            </td>

            <td className="px-4 py-4">
                <ReadinessBadge
                    ready={
                        contributor.logistics_ready
                    }
                    type="logistics"
                />
            </td>

            <td className="px-4 py-4">
                <ReadinessBadge
                    ready={
                        contributor.document_ready
                    }
                    type="document"
                />
            </td>

            <td className="px-4 py-4">
                {uniqueReasons.length === 0 ? (
                    <span className="text-sm text-muted-foreground">
                        Tidak ada blocker
                        yang ditampilkan.
                    </span>
                ) : (
                    <div className="flex max-w-md flex-wrap gap-2">
                        {uniqueReasons.map(
                            (reason) => (
                                <span
                                    key={
                                        reason
                                    }
                                    className="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    {reasonLabel(
                                        reason,
                                    )}
                                </span>
                            ),
                        )}
                    </div>
                )}
            </td>
        </tr>
    );
}

function ReadinessBadge({
    ready,
    type,
}) {
    const Icon =
        type === "logistics"
            ? Truck
            : FileCheck2;

    return (
        <span
            className={[
                "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
                ready
                    ? "bg-primary/10 text-primary"
                    : "bg-muted text-muted-foreground",
            ].join(" ")}
        >
            <Icon className="size-3.5" />

            {ready
                ? "Ready"
                : "Belum Ready"}
        </span>
    );
}

function SummaryCard({
    label,
    value,
    description,
    icon: Icon,
}) {
    return (
        <Card>
            <CardContent>
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {label}
                        </p>

                        <p className="mt-2 text-xl font-semibold text-foreground">
                            {value}
                        </p>

                        {description && (
                            <p className="mt-1 text-xs text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>

                    <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon className="size-4" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function EmptyContributors() {
    return (
        <div className="flex min-h-64 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                <Building2 className="size-5" />
            </div>

            <h2 className="mt-4 font-semibold text-foreground">
                Belum ada contributor efektif
            </h2>

            <p className="mt-1 max-w-lg text-sm leading-6 text-muted-foreground">
                Contributor hanya muncul ketika
                organisasi KDKMP memiliki current
                effective Safe Supply lebih dari
                nol pada Forecast ini.
            </p>
        </div>
    );
}


function procurementReasonLabel(code) {
    const labels = {
        FORECAST_NOT_PUBLISHED:
            "Forecast belum dipublikasikan",

        FORECAST_WINDOW_ENDED:
            "Periode operasional Forecast berakhir",

        VOLUME_NOT_READY:
            "Safe Supply belum memenuhi demand",

        NO_EFFECTIVE_CONTRIBUTORS:
            "Belum ada contributor efektif",

        LOGISTICS_NOT_READY:
            "Logistics contributor belum lengkap",

        DOCUMENT_NOT_READY:
            "Document contributor belum lengkap",
    };

    return labels[code] ?? code;
}

function formatDateTime(value) {
    if (!value) {
        return "—";
    }

    const date = new Date(value);

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
        return value;
    }

    return new Intl.DateTimeFormat(
        "id-ID",
        {
            dateStyle: "medium",
            timeStyle: "short",
        },
    ).format(date);
}


function reasonLabel(code) {
    const labels = {
        NOT_CURRENT_CONTRIBUTOR:
            "Bukan contributor aktif",

        CHECKLIST_MISSING:
            "Checklist belum tersedia",

        CHECKLIST_NOT_APPROVED:
            "Belum disetujui Manager",

        FORECAST_VERSION_STALE:
            "Forecast telah direvisi",

        REQUIRED_ITEM_UNSATISFIED:
            "Requirement belum terpenuhi",

        DOCUMENT_INVALID:
            "Dokumen tidak valid",

        FORECAST_WINDOW_ENDED:
            "Periode Forecast berakhir",
    };

    return labels[code] ?? code;
}

function formatNumber(
    value,
    precision = 6,
) {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return value ?? "—";
    }

    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits:
            precision,
    }).format(number);
}