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
    ShieldCheck,
} from "lucide-react";

export default function Dashboard({
    evaluatedAt,
    organization,
    summary,
    decisionGroups = [],
    supplyRisks = [],
    primaryForecasts = [],
}) {
    return (
        <>
            <Head title="Dashboard Manager — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Dashboard Manager"
                pageDescription="Pusat keputusan, approval, risiko pasokan, dan monitoring readiness KDKMP."
            >
                <div className="space-y-6">
                    <SummaryCards
                        summary={summary}
                    />

                    <DecisionQueue
                        groups={
                            decisionGroups
                        }
                    />

                    <SupplyRisks
                        risks={supplyRisks}
                    />

                    <ForecastMonitoring
                        forecasts={
                            primaryForecasts
                        }
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
            </KdkmpLayout>
        </>
    );
}

function SummaryCards({ summary = {} }) {
    const cards = [
        {
            label: "Menunggu Keputusan",
            value:
                summary.total_pending_decisions ??
                0,
            icon: ClipboardCheck,
        },
        {
            label: "Risiko Pasokan",
            value:
                summary.supply_risk_count ??
                0,
            icon: AlertTriangle,
        },
        {
            label: "Forecast PRIMARY",
            value:
                summary.primary_forecast_count ??
                0,
            icon: CalendarDays,
        },
        {
            label: "Ready for Procurement",
            value:
                summary.ready_for_procurement_count ??
                0,
            icon: ShieldCheck,
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

function DecisionQueue({ groups }) {
    const populatedGroups =
        groups.filter(
            (group) =>
                group.items?.length > 0,
        );

    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Queue Keputusan Manager
                </CardTitle>

                <CardDescription>
                    Payload tetap read-only pada
                    dashboard. Buka review untuk
                    menjalankan keputusan bisnis
                    eksplisit.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-0">
                {populatedGroups.length === 0 ? (
                    <EmptyDecisionState />
                ) : (
                    <div className="divide-y divide-border">
                        {populatedGroups.map(
                            (group) => (
                                <DecisionGroup
                                    key={
                                        group.key
                                    }
                                    group={
                                        group
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

function DecisionGroup({ group }) {
    return (
        <div className="p-5">
            <div className="flex items-center justify-between gap-4">
                <div>
                    <h3 className="font-semibold text-foreground">
                        {group.label}
                    </h3>

                    <p className="mt-1 text-sm text-muted-foreground">
                        {group.items.length} item
                        menunggu keputusan.
                    </p>
                </div>

                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.visit(
                            group.href,
                        )
                    }
                >
                    Lihat Semua
                    <ArrowRight data-icon="inline-end" />
                </Button>
            </div>

            <div className="mt-4 divide-y divide-border rounded-lg border border-border">
                {group.items
                    .slice(0, 3)
                    .map((item) => (
                        <DecisionItem
                            key={item.id}
                            item={item}
                        />
                    ))}
            </div>
        </div>
    );
}

function DecisionItem({ item }) {
    return (
        <button
            type="button"
            onClick={() =>
                router.visit(item.href)
            }
            className="flex w-full items-start gap-4 px-4 py-3 text-left transition hover:bg-muted/40"
        >
            <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <ClipboardCheck className="size-4" />
            </div>

            <div className="min-w-0 flex-1">
                <p className="font-medium text-foreground">
                    {item.title}
                </p>

                <p className="mt-1 text-sm text-muted-foreground">
                    {item.description}
                </p>

                {item.context && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {item.context}
                    </p>
                )}

                {item.time_at && (
                    <p className="mt-2 text-xs text-muted-foreground">
                        {item.time_label}:{" "}
                        {formatDateTime(
                            item.time_at,
                        )}
                    </p>
                )}
            </div>

            <ArrowRight className="mt-2 size-4 shrink-0 text-muted-foreground" />
        </button>
    );
}

function EmptyDecisionState() {
    return (
        <div className="flex min-h-48 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <CheckCircle2 className="size-5" />
            </div>

            <p className="mt-3 font-medium text-foreground">
                Tidak ada keputusan tertunda
            </p>

            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                Seluruh queue Manager saat ini
                sudah kosong.
            </p>
        </div>
    );
}

function SupplyRisks({ risks }) {
    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Risiko Pasokan Aktif
                </CardTitle>

                <CardDescription>
                    Commitment organisasi dengan
                    confidence Kuning atau Merah.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-0">
                {risks.length === 0 ? (
                    <div className="px-5 py-8 text-center text-sm text-muted-foreground">
                        Tidak ada Commitment
                        berisiko saat ini.
                    </div>
                ) : (
                    <div className="divide-y divide-border">
                        {risks.map((risk) => (
                            <button
                                key={risk.id}
                                type="button"
                                onClick={() =>
                                    router.visit(
                                        risk.href,
                                    )
                                }
                                className="flex w-full items-center gap-4 px-5 py-4 text-left transition hover:bg-muted/40"
                            >
                                <div
                                    className={
                                        risk.current_confidence ===
                                        "RED"
                                            ? "flex size-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-700"
                                            : "flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700"
                                    }
                                >
                                    <CircleAlert className="size-4" />
                                </div>

                                <div className="min-w-0 flex-1">
                                    <p className="font-medium text-foreground">
                                        {
                                            risk.producer_name
                                        }
                                    </p>

                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {
                                            risk.forecast_code
                                        }
                                        {" • "}
                                        {
                                            risk.commodity_name
                                        }
                                    </p>
                                </div>

                                <span
                                    className={
                                        risk.current_confidence ===
                                        "RED"
                                            ? "rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700"
                                            : "rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700"
                                    }
                                >
                                    {
                                        risk.current_confidence_label
                                    }
                                </span>

                                <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                            </button>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ForecastMonitoring({
    forecasts,
}) {
    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Monitoring Forecast & Supply
                </CardTitle>

                <CardDescription>
                    Current derived truth untuk
                    Forecast PUBLISHED saat KDKMP
                    menjadi PRIMARY.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-0">
                {forecasts.length === 0 ? (
                    <div className="px-5 py-8 text-center text-sm text-muted-foreground">
                        Belum ada Forecast PRIMARY
                        yang PUBLISHED.
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
        item.procurement_state;

    return (
        <div className="p-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-semibold text-foreground">
                            {
                                forecast.forecast_code
                            }
                        </p>

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
                        {
                            forecast
                                .sppg
                                .name
                        }
                    </p>
                </div>

                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.visit(
                            `/kdkmp/forecasts/${forecast.id}`,
                        )
                    }
                >
                    Detail
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
                />

                <Metric
                    label="Shortfall"
                    value={formatVolume(
                        state.shortfall,
                        forecast.unit,
                    )}
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
        </div>
    );
}

function Metric({ label, value }) {
    return (
        <div className="rounded-lg border border-border bg-muted/20 px-4 py-3">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>

            <p className="mt-1 text-base font-semibold text-foreground">
                {value}
            </p>
        </div>
    );
}

function ProcurementBadge({ ready }) {
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

function formatVolume(value, unit) {
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