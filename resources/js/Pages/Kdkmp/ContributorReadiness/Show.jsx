import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import KdkmpLayout from "@/Layouts/KdkmpLayout";
import {
    Head,
    router,
} from "@inertiajs/react";
import {
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    CircleAlert,
    FileCheck2,
    Truck,
} from "lucide-react";

export default function Show({
    forecast,
    readiness,
    checklists,
}) {
    return (
        <>
            <Head title="Contributor Readiness — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Contributor Readiness"
                pageDescription="Siapkan Logistics dan Document Readiness untuk kontribusi pasokan organisasi Anda."
            >
                <div className="space-y-6">
                    <ForecastContext
                        forecast={forecast}
                    />

                    <div className="grid gap-6 lg:grid-cols-2">
                        <ReadinessCard
                            type="logistics"
                            label="Logistics Readiness"
                            icon={Truck}
                            ready={
                                readiness.logistics_ready
                            }
                            reasons={
                                readiness.logistics_reason_codes ??
                                []
                            }
                            checklist={
                                checklists.logistics
                            }
                            forecastId={
                                forecast.id
                            }
                        />

                        <ReadinessCard
                            type="document"
                            label="Document Readiness"
                            icon={FileCheck2}
                            ready={
                                readiness.document_ready
                            }
                            reasons={
                                readiness.document_reason_codes ??
                                []
                            }
                            checklist={
                                checklists.document
                            }
                            forecastId={
                                forecast.id
                            }
                        />
                    </div>

                    <div className="rounded-xl border border-border bg-card px-4 py-3">
                        <p className="text-sm font-medium text-foreground">
                            Contributor-scoped view
                        </p>

                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                            Halaman ini hanya menampilkan
                            informasi yang diperlukan untuk
                            menyiapkan readiness organisasi
                            Anda. Data internal contributor
                            lain tidak ditampilkan.
                        </p>
                    </div>
                </div>
            </KdkmpLayout>
        </>
    );
}

function ForecastContext({
    forecast,
}) {
    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    {forecast.forecast_code}
                </CardTitle>

                <CardDescription>
                    Konteks Forecast untuk readiness
                    contributor.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-5">
                <div className="grid gap-5 md:grid-cols-3">
                    <ContextItem
                        label="SPPG"
                        value={
                            forecast.sppg.name
                        }
                        secondary={
                            forecast.sppg.code
                        }
                    />

                    <ContextItem
                        label="Komoditas"
                        value={
                            forecast
                                .commodity
                                .name
                        }
                        secondary={
                            forecast
                                .commodity
                                .code
                        }
                    />

                    <ContextItem
                        label="Periode Kebutuhan"
                        value={formatDateTime(
                            forecast.required_start_at,
                        )}
                        secondary={`s.d. ${formatDateTime(
                            forecast.required_end_at,
                        )}`}
                        icon={CalendarDays}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function ContextItem({
    label,
    value,
    secondary,
    icon: Icon = null,
}) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>

            <div className="mt-2 flex items-start gap-2">
                {Icon && (
                    <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                )}

                <div>
                    <p className="font-medium text-foreground">
                        {value}
                    </p>

                    {secondary && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {secondary}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

function ReadinessCard({
    type,
    label,
    icon: Icon,
    ready,
    reasons,
    checklist,
    forecastId,
}) {
    const waitingManager =
        checklist?.status ===
        "PENDING_APPROVAL";

    return (
        <Card>
            <CardHeader className="border-b">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Icon className="size-5" />
                        </div>

                        <div>
                            <CardTitle>
                                {label}
                            </CardTitle>

                            <CardDescription className="mt-1">
                                {readinessDescription(
                                    type,
                                )}
                            </CardDescription>
                        </div>
                    </div>

                    <ReadinessBadge
                        ready={ready}
                        waiting={
                            waitingManager
                        }
                    />
                </div>
            </CardHeader>

            <CardContent className="p-5">
                {checklist ? (
                    <ChecklistContext
                        checklist={
                            checklist
                        }
                    />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Checklist belum
                        disiapkan untuk Forecast
                        ini.
                    </p>
                )}

                {!ready &&
                    !waitingManager &&
                    reasons.length > 0 && (
                        <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50/60 px-4 py-3">
                            <p className="text-sm font-medium text-amber-900">
                                Perlu ditindaklanjuti
                            </p>

                            <ul className="mt-2 space-y-1 text-sm text-amber-800">
                                {reasons.map(
                                    (reason) => (
                                        <li
                                            key={
                                                reason
                                            }
                                        >
                                            •{" "}
                                            {reasonLabel(
                                                reason,
                                            )}
                                        </li>
                                    ),
                                )}
                            </ul>
                        </div>
                    )}

                <div className="mt-5">
                    {checklist ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.visit(
                                    `/kdkmp/readiness/${checklist.id}`,
                                )
                            }
                        >
                            Buka Checklist
                            <ArrowRight data-icon="inline-end" />
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            onClick={() =>
                                prepareChecklist(
                                    forecastId,
                                    type,
                                )
                            }
                        >
                            Siapkan Checklist
                            <ArrowRight data-icon="inline-end" />
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function ChecklistContext({
    checklist,
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div>
                <p className="text-xs text-muted-foreground">
                    Version
                </p>

                <p className="mt-1 text-sm font-medium text-foreground">
                    v{checklist.version_no}
                </p>
            </div>

            <div>
                <p className="text-xs text-muted-foreground">
                    Status
                </p>

                <p className="mt-1 text-sm font-medium text-foreground">
                    {statusLabel(
                        checklist.status,
                    )}
                </p>
            </div>
        </div>
    );
}

function ReadinessBadge({
    ready,
    waiting,
}) {
    if (ready) {
        return (
            <span className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                <CheckCircle2 className="size-3.5" />
                Siap
            </span>
        );
    }

    if (waiting) {
        return (
            <span className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                <CircleAlert className="size-3.5" />
                Menunggu Manager
            </span>
        );
    }

    return (
        <span className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
            <CircleAlert className="size-3.5" />
            Belum Siap
        </span>
    );
}

function prepareChecklist(
    forecastId,
    type,
) {
    router.post(
        `/kdkmp/forecasts/${forecastId}/readiness/${type}/prepare`,
    );
}

function readinessDescription(type) {
    return type === "logistics"
        ? "Kesiapan operasional dan pengiriman contributor."
        : "Kelengkapan dokumen yang menjadi gate contributor.";
}

function statusLabel(status) {
    const labels = {
        DRAFT:
            "Draft",

        PENDING_APPROVAL:
            "Menunggu Persetujuan",

        APPROVED:
            "Disetujui",

        REJECTED:
            "Ditolak",
    };

    return (
        labels[status] ??
        status ??
        "—"
    );
}

function reasonLabel(reason) {
    const labels = {
        CHECKLIST_MISSING:
            "Checklist belum disiapkan.",

        CHECKLIST_NOT_APPROVED:
            "Checklist belum memperoleh approval Manager.",

        FORECAST_VERSION_STALE:
            "Checklist berasal dari versi Forecast sebelumnya dan perlu diperbarui.",

        EMPTY_CHECKLIST:
            "Checklist belum memiliki item.",

        REQUIRED_ITEM_UNSATISFIED:
            "Masih ada item wajib yang belum terpenuhi.",

        DOCUMENT_MISSING:
            "Dokumen wajib belum tersedia.",

        DOCUMENT_REVISION_MISSING:
            "Revision dokumen belum tercatat.",

        DOCUMENT_INVALID:
            "Dokumen yang digunakan tidak lagi valid.",

        FORECAST_WINDOW_ENDED:
            "Periode Forecast sudah berakhir.",

        FORECAST_NOT_PUBLISHED:
            "Forecast tidak lagi PUBLISHED.",

        NOT_CURRENT_CONTRIBUTOR:
            "Organisasi tidak lagi menjadi current contributor.",
    };

    return (
        labels[reason] ??
        reason
    );
}

function formatDateTime(value) {
    if (!value) {
        return "—";
    }

    return new Intl.DateTimeFormat(
        "id-ID",
        {
            dateStyle: "medium",
            timeStyle: "short",
        },
    ).format(new Date(value));
}