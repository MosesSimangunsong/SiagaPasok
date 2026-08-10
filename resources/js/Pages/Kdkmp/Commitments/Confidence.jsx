import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import KdkmpLayout from "@/Layouts/KdkmpLayout";
import { Head, router } from "@inertiajs/react";
import {
    CheckCircle2,
    CircleHelp,
    CircleX,
    Eye,
    ShieldCheck,
    TriangleAlert,
} from "lucide-react";

export default function Confidence({
    commitments,
}) {
    const greenCount =
        commitments.filter(
            (item) =>
                item.current_confidence ===
                "GREEN",
        ).length;

    const yellowCount =
        commitments.filter(
            (item) =>
                item.current_confidence ===
                "YELLOW",
        ).length;

    const redCount =
        commitments.filter(
            (item) =>
                item.current_confidence ===
                "RED",
        ).length;

    return (
        <>
            <Head title="Monitoring Confidence — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Monitoring Confidence"
                pageDescription="Pantau kondisi commitment approved dan identifikasi pasokan yang membutuhkan tindakan."
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <ConfidenceMetric
                            label="Aman"
                            value={greenCount}
                            state="GREEN"
                        />

                        <ConfidenceMetric
                            label="Berisiko"
                            value={
                                yellowCount
                            }
                            state="YELLOW"
                        />

                        <ConfidenceMetric
                            label="Kritis"
                            value={redCount}
                            state="RED"
                        />
                    </div>

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Kondisi Commitment
                            </CardTitle>

                            <CardDescription>
                                Confidence berlaku per
                                commitment dan bukan
                                reputasi permanen
                                produsen.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="p-0">
                            {commitments.length ===
                            0 ? (
                                <EmptyState />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[1180px] text-left text-sm">
                                        <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Forecast
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Produsen
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Active
                                                    Range
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Confidence
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Workflow
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Verifikasi
                                                    Terakhir
                                                </th>

                                                <th className="px-4 py-3 text-right font-medium">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {commitments.map(
                                                (
                                                    commitment,
                                                ) => (
                                                    <tr
                                                        key={
                                                            commitment.id
                                                        }
                                                    >
                                                        <td className="px-4 py-4">
                                                            <p className="font-medium text-foreground">
                                                                {
                                                                    commitment
                                                                        .forecast
                                                                        .forecast_code
                                                                }
                                                            </p>

                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {
                                                                    commitment
                                                                        .commodity
                                                                        .name
                                                                }{" "}
                                                                •{" "}
                                                                {
                                                                    commitment
                                                                        .forecast
                                                                        .sppg_name
                                                                }
                                                            </p>
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <p className="font-medium text-foreground">
                                                                {
                                                                    commitment
                                                                        .producer
                                                                        .name
                                                                }
                                                            </p>

                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {
                                                                    commitment
                                                                        .producer
                                                                        .producer_code
                                                                }
                                                            </p>
                                                        </td>

                                                        <td className="px-4 py-4 font-medium text-foreground">
                                                            {commitment.active_version
                                                                ? formatRange(
                                                                      commitment.active_version,
                                                                  )
                                                                : "—"}
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <ConfidenceBadge
                                                                value={
                                                                    commitment.current_confidence
                                                                }
                                                                label={
                                                                    commitment.current_confidence_label
                                                                }
                                                            />
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <span className="text-sm text-foreground">
                                                                {
                                                                    commitment
                                                                        .workflow_state
                                                                        ?.label
                                                                }
                                                            </span>
                                                        </td>

                                                        <td className="px-4 py-4 text-muted-foreground">
                                                            {formatDateTime(
                                                                commitment.last_confidence_verified_at,
                                                            )}
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <div className="flex justify-end">
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        router.visit(
                                                                            `/kdkmp/commitments/${commitment.id}`,
                                                                        )
                                                                    }
                                                                >
                                                                    <Eye data-icon="inline-start" />
                                                                    Detail
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </KdkmpLayout>
        </>
    );
}

function ConfidenceMetric({
    label,
    value,
    state,
}) {
    const config = {
        GREEN: {
            icon: CheckCircle2,
            className:
                "text-green-700 dark:text-green-400",
        },

        YELLOW: {
            icon: TriangleAlert,
            className:
                "text-amber-700 dark:text-amber-400",
        },

        RED: {
            icon: CircleX,
            className:
                "text-red-700 dark:text-red-400",
        },
    };

    const selected =
        config[state];

    const Icon = selected.icon;

    return (
        <Card>
            <CardContent>
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {label}
                        </p>

                        <p className="mt-2 text-2xl font-semibold text-foreground">
                            {value}
                        </p>
                    </div>

                    <Icon
                        className={`size-6 ${selected.className}`}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function EmptyState() {
    return (
        <div className="flex min-h-64 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <ShieldCheck className="size-5" />
            </div>

            <h2 className="mt-4 font-semibold text-foreground">
                Belum ada Confidence
                Pasokan
            </h2>

            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                Confidence akan muncul setelah
                Commitment memperoleh approval
                pertama dari Manager.
            </p>
        </div>
    );
}

function ConfidenceBadge({
    value,
    label,
}) {
    const config = {
        GREEN: {
            icon: CheckCircle2,
            className:
                "bg-green-500/10 text-green-700 dark:text-green-400",
        },

        YELLOW: {
            icon: TriangleAlert,
            className:
                "bg-amber-500/10 text-amber-700 dark:text-amber-400",
        },

        RED: {
            icon: CircleX,
            className:
                "bg-red-500/10 text-red-700 dark:text-red-400",
        },
    };

    const selected =
        config[value];

    if (!selected) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                <CircleHelp className="size-3.5" />
                Belum ada
            </span>
        );
    }

    const Icon = selected.icon;

    return (
        <span
            className={[
                "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
                selected.className,
            ].join(" ")}
        >
            <Icon className="size-3.5" />
            {label ?? value}
        </span>
    );
}

function formatRange(version) {
    return `${formatNumber(
        version.min_volume,
        version.unit?.decimal_precision,
    )}–${formatNumber(
        version.max_volume,
        version.unit?.decimal_precision,
    )} ${version.unit?.symbol ?? ""}`;
}

function formatNumber(
    value,
    precision = 2,
) {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return value ?? "—";
    }

    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits:
            precision ?? 2,
    }).format(number);
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