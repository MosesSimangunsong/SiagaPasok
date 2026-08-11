import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import SppgLayout from "@/Layouts/SppgLayout";
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
    ClipboardList,
    PackageCheck,
    Plus,
    ShieldCheck,
} from "lucide-react";

export default function Dashboard({
    evaluatedAt,
    organization,
    summary = {},
    forecasts = [],
}) {
    return (
        <>
            <Head title="Dashboard SPPG — SiagaPasok" />

            <SppgLayout
                pageTitle="Dashboard SPPG"
                pageDescription="Pantau kecukupan pasokan lokal dan kesiapan kebutuhan SPPG yang akan datang."
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        onClick={() =>
                            router.visit(
                                "/sppg/forecasts/create",
                            )
                        }
                    >
                        <Plus data-icon="inline-start" />
                        Buat Forecast
                    </Button>
                }
            >
                <div className="space-y-6">
                    <SummaryCards
                        summary={summary}
                    />

                    {summary.draft_forecast_count >
                        0 && (
                        <DraftNotice
                            count={
                                summary.draft_forecast_count
                            }
                        />
                    )}

                    <Forecasts
                        forecasts={forecasts}
                    />

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
            </SppgLayout>
        </>
    );
}

function SummaryCards({
    summary,
}) {
    const cards = [
        {
            label: "Forecast Aktif",
            value:
                summary.active_forecast_count ??
                0,
            icon: ClipboardList,
        },
        {
            label: "Perlu Perhatian",
            value:
                summary.attention_forecast_count ??
                0,
            icon: AlertTriangle,
        },
        {
            label: "Ready for Procurement",
            value:
                summary.ready_for_procurement_count ??
                0,
            icon: ShieldCheck,
        },
        {
            label: "Draft Forecast",
            value:
                summary.draft_forecast_count ??
                0,
            icon: PackageCheck,
        },
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {cards.map((card) => {
                const Icon = card.icon;

                return (
                    <Card key={card.label}>
                        <CardContent className="flex items-center justify-between p-5">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    {card.label}
                                </p>

                                <p className="mt-2 text-2xl font-semibold tracking-tight text-foreground">
                                    {card.value}
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

function DraftNotice({ count }) {
    return (
        <div className="flex flex-col gap-4 rounded-xl border border-amber-200 bg-amber-50/60 px-5 py-4 sm:flex-row sm:items-center">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                <CircleAlert className="size-5" />
            </div>

            <div className="flex-1">
                <p className="font-medium text-amber-950">
                    {count} Draft Forecast belum
                    dipublikasikan
                </p>

                <p className="mt-1 text-sm text-amber-800">
                    Draft belum menjadi kebutuhan
                    operasional KDKMP dan belum
                    memiliki derived supply state.
                </p>
            </div>

            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() =>
                    router.visit(
                        "/sppg/forecasts",
                    )
                }
            >
                Buka Forecast
                <ArrowRight data-icon="inline-end" />
            </Button>
        </div>
    );
}

function Forecasts({
    forecasts,
}) {
    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Kebutuhan Aktif & Mendatang
                </CardTitle>

                <CardDescription>
                    Aggregate supply dan readiness
                    untuk Forecast PUBLISHED yang
                    masih berada pada atau menuju
                    periode kebutuhan.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-0">
                {forecasts.length === 0 ? (
                    <EmptyForecasts />
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

function EmptyForecasts() {
    return (
        <div className="flex min-h-56 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <ClipboardList className="size-5" />
            </div>

            <p className="mt-4 font-semibold text-foreground">
                Belum ada Forecast aktif
            </p>

            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                Buat atau publikasikan Forecast
                untuk mulai memantau kecukupan
                pasokan lokal.
            </p>

            <Button
                type="button"
                className="mt-5"
                onClick={() =>
                    router.visit(
                        "/sppg/forecasts",
                    )
                }
            >
                Buka Forecast
                <ArrowRight data-icon="inline-end" />
            </Button>
        </div>
    );
}

function ForecastItem({ item }) {
    const {
        forecast,
        supply,
        procurement,
        contributors = [],
    } = item;

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
                                procurement.ready_for_procurement
                            }
                        />
                    </div>

                    <p className="mt-1 text-sm text-muted-foreground">
                        {
                            forecast
                                .commodity
                                .name
                        }
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

                <div className="flex flex-wrap gap-2">
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
                        Detail Forecast
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                `/sppg/forecasts/${forecast.id}/readiness`,
                            )
                        }
                    >
                        Detail Readiness
                        <ArrowRight data-icon="inline-end" />
                    </Button>
                </div>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Metric
                    label="Demand"
                    value={formatVolume(
                        supply.demand_target,
                        forecast.unit,
                    )}
                />

                <Metric
                    label="Safe Supply"
                    value={formatVolume(
                        supply.total_safe_supply,
                        forecast.unit,
                    )}
                />

                <Metric
                    label="At-Risk"
                    value={formatVolume(
                        supply.at_risk_supply,
                        forecast.unit,
                    )}
                    state={
                        Number(
                            supply.at_risk_supply,
                        ) > 0
                            ? "warning"
                            : null
                    }
                />

                <Metric
                    label="Shortfall"
                    value={formatVolume(
                        supply.shortfall,
                        forecast.unit,
                    )}
                    state={
                        Number(
                            supply.shortfall,
                        ) > 0
                            ? "critical"
                            : null
                    }
                />

                <Metric
                    label="Coverage"
                    value={
                        supply.coverage_percent ===
                            null ||
                        supply.coverage_percent ===
                            undefined
                            ? "—"
                            : `${formatNumber(
                                  supply.coverage_percent,
                                  2,
                              )}%`
                    }
                />
            </div>

            <ReadinessSummary
                procurement={procurement}
            />

            <ContributorMatrix
                contributors={
                    contributors
                }
            />

            {!procurement.ready_for_procurement &&
                procurement.reason_codes
                    ?.length > 0 && (
                    <RfpBlockers
                        reasons={
                            procurement.reason_codes
                        }
                    />
                )}
        </div>
    );
}

function Metric({
    label,
    value,
    state = null,
}) {
    const valueClass =
        state === "critical"
            ? "text-red-700"
            : state === "warning"
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

function ReadinessSummary({
    procurement,
}) {
    return (
        <div className="mt-4 flex flex-wrap gap-2">
            <GateBadge
                label="Volume"
                ready={
                    procurement.volume_ready
                }
            />

            <GateBadge
                label="Logistics Contributor"
                ready={
                    procurement.all_contributors_logistics_ready
                }
            />

            <GateBadge
                label="Document Contributor"
                ready={
                    procurement.all_contributors_document_ready
                }
            />
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

function ContributorMatrix({
    contributors,
}) {
    return (
        <div className="mt-5">
            <p className="text-sm font-medium text-foreground">
                Contributor Readiness
            </p>

            {contributors.length === 0 ? (
                <div className="mt-3 rounded-lg border border-dashed border-border px-4 py-4 text-sm text-muted-foreground">
                    Belum ada KDKMP dengan
                    effective Safe Supply pada
                    Forecast ini.
                </div>
            ) : (
                <div className="mt-3 overflow-x-auto rounded-lg border border-border">
                    <table className="w-full min-w-[560px] text-left text-sm">
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
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-border">
                            {contributors.map(
                                (
                                    contributor,
                                ) => (
                                    <tr
                                        key={
                                            contributor
                                                .organization
                                                .id
                                        }
                                    >
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-foreground">
                                                {
                                                    contributor
                                                        .organization
                                                        .name
                                                }
                                            </p>

                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {
                                                    contributor
                                                        .organization
                                                        .code
                                                }
                                            </p>
                                        </td>

                                        <td className="px-4 py-3">
                                            <SimpleState
                                                ready={
                                                    contributor.logistics_ready
                                                }
                                            />
                                        </td>

                                        <td className="px-4 py-3">
                                            <SimpleState
                                                ready={
                                                    contributor.document_ready
                                                }
                                            />
                                        </td>
                                    </tr>
                                ),
                            )}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function SimpleState({ ready }) {
    return ready ? (
        <span className="inline-flex items-center gap-1.5 text-emerald-700">
            <CheckCircle2 className="size-4" />
            Siap
        </span>
    ) : (
        <span className="inline-flex items-center gap-1.5 text-amber-700">
            <CircleAlert className="size-4" />
            Belum Siap
        </span>
    );
}

function RfpBlockers({ reasons }) {
    return (
        <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50/60 px-4 py-3">
            <div className="flex items-start gap-2">
                <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-700" />

                <div>
                    <p className="text-sm font-medium text-amber-950">
                        Belum Ready for Procurement
                    </p>

                    <ul className="mt-1 space-y-1 text-sm text-amber-800">
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
            </div>
        </div>
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
            <CircleAlert className="size-3.5" />
            Belum Ready
        </span>
    );
}

function reasonLabel(reason) {
    const labels = {
        FORECAST_NOT_PUBLISHED:
            "Forecast tidak PUBLISHED.",

        FORECAST_WINDOW_ENDED:
            "Periode kebutuhan sudah berakhir.",

        VOLUME_NOT_READY:
            "Safe Supply belum memenuhi Demand Target.",

        NO_EFFECTIVE_CONTRIBUTORS:
            "Belum ada contributor dengan effective Safe Supply.",

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