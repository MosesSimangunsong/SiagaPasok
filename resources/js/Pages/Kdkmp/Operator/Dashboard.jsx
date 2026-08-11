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
    AlertTriangle,
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    CircleAlert,
    ClipboardCheck,
    Handshake,
    Network,
    PackageCheck,
    ShieldAlert,
    Sprout,
} from "lucide-react";

export default function Dashboard({
    evaluatedAt,
    organization,
    summary,
    primaryForecasts = [],
    actionQueue = [],
    upcomingHarvests = [],
    activeFallback = {},
}) {
    const requesterRequests =
        activeFallback?.requesterRequests ?? [];

    const networkRequests =
        activeFallback?.networkRequests ?? [];

    const supplierOffers =
        activeFallback?.supplierOffers ?? [];

    return (
        <>
            <Head title="Dashboard Operator — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Dashboard Operator"
                pageDescription="Pantau kebutuhan SPPG, risiko pasokan, readiness, dan pekerjaan operasional yang perlu ditindaklanjuti."
            >
                <div className="space-y-6">
                    <DashboardSummary
                        summary={summary}
                    />

                    <ActionQueue
                        actions={actionQueue}
                    />

                    <ForecastSection
                        forecasts={primaryForecasts}
                    />

                    <div className="grid gap-6 xl:grid-cols-2">
                        <UpcomingHarvests
                            harvests={
                                upcomingHarvests
                            }
                        />

                        <FallbackOverview
                            requesterRequests={
                                requesterRequests
                            }
                            networkRequests={
                                networkRequests
                            }
                            supplierOffers={
                                supplierOffers
                            }
                        />
                    </div>

                    <div className="rounded-xl border border-border bg-card px-4 py-3">
                        <p className="text-xs text-muted-foreground">
                            Evaluasi terakhir:{" "}
                            {formatDateTime(
                                evaluatedAt,
                            )}
                            {organization?.name
                                ? ` • ${organization.name}`
                                : ""}
                        </p>
                    </div>
                </div>
            </KdkmpLayout>
        </>
    );
}

function DashboardSummary({
    summary = {},
}) {
    const items = [
        {
            label: "Forecast Aktif",
            value:
                summary.active_forecast_count ??
                0,
            icon: ClipboardCheck,
        },
        {
            label: "Perlu Tindakan",
            value:
                summary.action_count ?? 0,
            icon: AlertTriangle,
        },
        {
            label: "Broadcast Fallback",
            value:
                summary.network_request_count ??
                0,
            icon: Network,
        },
        {
            label: "Panen Mendatang",
            value:
                summary.upcoming_harvest_count ??
                0,
            icon: Sprout,
        },
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {items.map((item) => {
                const Icon = item.icon;

                return (
                    <Card key={item.label}>
                        <CardContent className="flex items-center justify-between p-5">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    {item.label}
                                </p>

                                <p className="mt-2 text-2xl font-semibold tracking-tight text-foreground">
                                    {item.value}
                                </p>
                            </div>

                            <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Icon className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}

function ActionQueue({ actions }) {
    const orderedActions = [
        ...actions,
    ].sort(
        (left, right) =>
            severityRank(
                left.severity,
            ) -
            severityRank(
                right.severity,
            ),
    );

    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Prioritas Operasional
                </CardTitle>

                <CardDescription>
                    Tindakan yang berasal dari
                    kondisi operasional saat ini.
                    Item yang menunggu keputusan
                    Manager tidak dihitung sebagai
                    pekerjaan Operator.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-0">
{orderedActions.length === 0 ? (
                    <div className="flex min-h-44 flex-col items-center justify-center px-6 text-center">
                        <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <CheckCircle2 className="size-5" />
                        </div>

                        <p className="mt-3 font-medium text-foreground">
                            Tidak ada tindakan
                            mendesak
                        </p>

                        <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                            Belum ada pekerjaan
                            operasional yang perlu
                            ditindaklanjuti saat ini.
                        </p>
                    </div>
                ) : (
                    <div className="divide-y divide-border">
                        {orderedActions.map(
                            (action, index) => (
                                <ActionItem
                                    key={`${action.kind}-${index}`}
                                    action={
                                        action
                                    }
                                />
                            ),
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ActionItem({ action }) {
    const severity =
        severityPresentation(
            action.severity,
        );

    const Icon = severity.icon;

    return (
        <div className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center">
            <div
                className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${severity.containerClass}`}
            >
                <Icon
                    className={`size-5 ${severity.iconClass}`}
                />
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="font-medium text-foreground">
                        {action.title}
                    </p>

                    <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${severity.badgeClass}`}
                    >
                        {severity.label}
                    </span>
                </div>

                {action.description && (
                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                        {action.description}
                    </p>
                )}
            </div>

            {action.href && (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.visit(
                            action.href,
                        )
                    }
                >
                    Buka
                    <ArrowRight data-icon="inline-end" />
                </Button>
            )}
        </div>
    );
}

function ForecastSection({
    forecasts,
}) {
    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Forecast & Kondisi Pasokan
                </CardTitle>

                <CardDescription>
                    Snapshot kebutuhan dan kesiapan
                    procurement dari Forecast
                    PUBLISHED yang menempatkan KDKMP
                    ini sebagai PRIMARY aktif.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-0">
                {forecasts.length === 0 ? (
                    <div className="flex min-h-52 flex-col items-center justify-center px-6 text-center">
                        <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <PackageCheck className="size-5" />
                        </div>

                        <p className="mt-3 font-medium text-foreground">
                            Belum ada Forecast aktif
                        </p>

                        <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                            Forecast akan muncul
                            setelah SPPG terkait
                            memiliki Forecast PUBLISHED
                            dan organisasi Anda menjadi
                            PRIMARY aktif.
                        </p>
                    </div>
                ) : (
                    <div className="divide-y divide-border">
                        {forecasts.map(
                            (item) => (
                                <ForecastItem
                                    key={
                                        item
                                            .forecast
                                            .id
                                    }
                                    item={
                                        item
                                    }
                                />
                            ),
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ForecastItem({ item }) {
    const forecast =
        item.forecast;

    const state =
        item.procurement_state ?? {};

    return (
        <div className="p-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-semibold text-foreground">
                            {
                                forecast.forecast_code
                            }
                        </h3>

                        <ProcurementBadge
                            ready={
                                state.ready_for_procurement
                            }
                        />
                    </div>

                    <p className="mt-1 text-sm text-muted-foreground">
                        {
                            forecast
                                .commodity
                                .name
                        }
                        {" • "}
                        {forecast.sppg.name}
                    </p>

                    <p className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                        <CalendarDays className="size-3.5" />

                        {formatDateTime(
                            forecast.required_start_at,
                        )}
                        {" — "}
                        {formatDateTime(
                            forecast.required_end_at,
                        )}
                    </p>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        router.visit(
                            `/kdkmp/forecasts/${forecast.id}`,
                        )
                    }
                >
                    Detail Forecast
                    <ArrowRight data-icon="inline-end" />
                </Button>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Metric
                    label="Demand"
                    value={formatVolume(
                        state.demand_target,
                        forecast.unit,
                    )}
                />

                <Metric
                    label="Safe Supply"
                    value={formatVolume(
                        state.total_safe_supply,
                        forecast.unit,
                    )}
                />

                <Metric
                    label="At-Risk"
                    value={formatVolume(
                        state.at_risk_supply,
                        forecast.unit,
                    )}
                    emphasis={
                        Number(
                            state.at_risk_supply,
                        ) > 0
                            ? "warning"
                            : null
                    }
                />

                <Metric
                    label="Shortfall"
                    value={formatVolume(
                        state.shortfall,
                        forecast.unit,
                    )}
                    emphasis={
                        Number(
                            state.shortfall,
                        ) > 0
                            ? "critical"
                            : null
                    }
                />

                <Metric
                    label="Coverage"
                    value={
                        state.coverage_percent ===
                        null ||
                        state.coverage_percent ===
                            undefined
                            ? "—"
                            : `${formatNumber(
                                  state.coverage_percent,
                                  2,
                              )}%`
                    }
                />
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
                <GateBadge
                    label="Volume"
                    ready={
                        state.volume_ready
                    }
                />

                <GateBadge
                    label="Logistics"
                    ready={
                        state.all_contributors_logistics_ready
                    }
                />

                <GateBadge
                    label="Document"
                    ready={
                        state.all_contributors_document_ready
                    }
                />
            </div>

            {!state.ready_for_procurement &&
    state.reason_codes?.length > 0 && (
        <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50/60 px-4 py-3">
            <div className="flex items-start gap-2">
                <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-700" />

                <div>
                    <p className="text-sm font-medium text-amber-900">
                        Penghambat Ready for
                        Procurement
                    </p>

                    <ul className="mt-1 space-y-1 text-sm text-amber-800">
                        {state.reason_codes.map(
                            (reason) => (
                                <li
                                    key={
                                        reason
                                    }
                                >
                                    •{" "}
                                    {rfpReasonLabel(
                                        reason,
                                    )}
                                </li>
                            ),
                        )}
                    </ul>
                </div>
            </div>
        </div>
    )}
        </div>
    );
    function rfpReasonLabel(reason) {
    const labels = {
        FORECAST_NOT_PUBLISHED:
            "Forecast tidak lagi berstatus PUBLISHED.",

        FORECAST_WINDOW_ENDED:
            "Periode kebutuhan Forecast sudah berakhir.",

        VOLUME_NOT_READY:
            "Safe Supply belum memenuhi Demand.",

        NO_EFFECTIVE_CONTRIBUTORS:
            "Belum ada KDKMP dengan effective Safe Supply.",

        LOGISTICS_NOT_READY:
            "Logistics Readiness seluruh contributor belum terpenuhi.",

        DOCUMENT_NOT_READY:
            "Document Readiness seluruh contributor belum terpenuhi.",
    };

    return (
        labels[reason] ??
        reason
    );
}
}

function Metric({
    label,
    value,
    emphasis = null,
}) {
    const valueClass =
        emphasis === "critical"
            ? "text-destructive"
            : emphasis === "warning"
              ? "text-amber-700"
              : "text-foreground";

    return (
        <div className="rounded-lg border border-border bg-muted/20 px-4 py-3">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>

            <p
                className={`mt-1 text-lg font-semibold ${valueClass}`}
            >
                {value}
            </p>
        </div>
    );
}

function GateBadge({
    label,
    ready,
}) {
    return (
        <span
            className={
                ready
                    ? "inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"
                    : "inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700"
            }
        >
            {ready ? (
                <CheckCircle2 className="size-3.5" />
            ) : (
                <CircleAlert className="size-3.5" />
            )}

            {label}:{" "}
            {ready
                ? "Siap"
                : "Belum Siap"}
        </span>
    );
}

function ProcurementBadge({
    ready,
}) {
    return ready ? (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
            <CheckCircle2 className="size-3.5" />
            Ready for Procurement
        </span>
    ) : (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
            <ShieldAlert className="size-3.5" />
            Belum Ready
        </span>
    );
}

function UpcomingHarvests({
    harvests,
}) {
    return (
        <Card>
            <CardHeader className="border-b">
<CardTitle>
    Expected Harvest Aktif/Mendatang
</CardTitle>

<CardDescription>
    Indikasi panen yang masih berada
    pada atau menuju periode panen.
    Data ini adalah konteks
    perencanaan dan bukan Safe Supply.
</CardDescription>
            </CardHeader>

            <CardContent className="p-0">
                {harvests.length === 0 ? (
                    <div className="px-5 py-8 text-center text-sm text-muted-foreground">
                        Belum ada Expected Harvest
                        mendatang.
                    </div>
                ) : (
                    <div className="divide-y divide-border">
                        {harvests.map(
                            (harvest) => (
                                <div
                                    key={
                                        harvest.id
                                    }
                                    className="px-5 py-4"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="font-medium text-foreground">
                                                {
                                                    harvest
                                                        .commodity
                                                        .name
                                                }
                                            </p>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {
                                                    harvest
                                                        .producer
                                                        .name
                                                }
                                            </p>
                                        </div>

                                        <p className="text-sm font-medium text-foreground">
                                            {formatRange(
                                                harvest,
                                            )}
                                        </p>
                                    </div>

                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {formatDateTime(
                                            harvest.harvest_start_at,
                                        )}
                                        {" — "}
                                        {formatDateTime(
                                            harvest.harvest_end_at,
                                        )}
                                    </p>
                                </div>
                            ),
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function FallbackOverview({
    requesterRequests,
    networkRequests,
    supplierOffers,
}) {
    const total =
        requesterRequests.length +
        networkRequests.length +
        supplierOffers.length;

    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Aktivitas Fallback
                </CardTitle>

                <CardDescription>
                    Request milik organisasi,
                    broadcast jaringan, dan Offer
                    supplier yang masih aktif.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-5">
                {total === 0 ? (
                    <div className="py-4 text-center">
                        <Handshake className="mx-auto size-8 text-muted-foreground" />

                        <p className="mt-3 text-sm text-muted-foreground">
                            Belum ada aktivitas
                            fallback aktif.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <FallbackRow
                            label="Request PRIMARY"
                            value={
                                requesterRequests.length
                            }
                            href="/kdkmp/fallback-requests"
                        />

                        <FallbackRow
                            label="Broadcast NETWORK"
                            value={
                                networkRequests.length
                            }
                            href="/kdkmp/fallback-network"
                        />

                        <FallbackRow
                            label="Offer Supplier"
                            value={
                                supplierOffers.length
                            }
                            href="/kdkmp/fallback-offers"
                        />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function FallbackRow({
    label,
    value,
    href,
}) {
    return (
        <button
            type="button"
            onClick={() =>
                router.visit(href)
            }
            className="flex w-full items-center justify-between rounded-lg border border-border px-4 py-3 text-left transition hover:bg-muted/50"
        >
            <span className="text-sm font-medium text-foreground">
                {label}
            </span>

            <span className="flex items-center gap-2">
                <span className="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                    {value}
                </span>

                <ArrowRight className="size-4 text-muted-foreground" />
            </span>
        </button>
    );
}


function severityRank(severity) {
    if (severity === "CRITICAL") {
        return 0;
    }

    if (severity === "ATTENTION") {
        return 1;
    }

    return 2;
}


function severityPresentation(
    severity,
) {
    if (severity === "CRITICAL") {
        return {
            label: "Kritis",
            icon: CircleAlert,
            containerClass:
                "bg-red-50",
            iconClass:
                "text-red-700",
            badgeClass:
                "bg-red-50 text-red-700",
        };
    }

    if (severity === "ATTENTION") {
        return {
            label: "Perlu perhatian",
            icon: AlertTriangle,
            containerClass:
                "bg-amber-50",
            iconClass:
                "text-amber-700",
            badgeClass:
                "bg-amber-50 text-amber-700",
        };
    }

    return {
        label: "Informasi",
        icon: Network,
        containerClass:
            "bg-primary/10",
        iconClass:
            "text-primary",
        badgeClass:
            "bg-primary/10 text-primary",
    };
}

function formatVolume(
    value,
    unit,
) {
    if (
        value === null ||
        value === undefined
    ) {
        return "—";
    }

    return `${formatNumber(
        value,
        unit?.decimal_precision ?? 2,
    )} ${unit?.symbol ?? ""}`.trim();
}

function formatRange(harvest) {
    return `${formatNumber(
        harvest.expected_min_volume,
        harvest.unit?.decimal_precision ??
            2,
    )}–${formatNumber(
        harvest.expected_max_volume,
        harvest.unit?.decimal_precision ??
            2,
    )} ${harvest.unit?.symbol ?? ""}`;
}

function formatNumber(
    value,
    maximumFractionDigits = 2,
) {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return value ?? "—";
    }

    return new Intl.NumberFormat(
        "id-ID",
        {
            maximumFractionDigits,
        },
    ).format(number);
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