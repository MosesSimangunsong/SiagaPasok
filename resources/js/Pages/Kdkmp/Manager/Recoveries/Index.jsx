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
    Eye,
    RefreshCcw,
    TriangleAlert,
} from "lucide-react";

export default function Index({
    recoveries,
}) {
    return (
        <>
            <Head title="Recovery Review — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Recovery Review"
                pageDescription="Review permintaan pemulihan Confidence YELLOW menjadi GREEN."
            >
                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>
                            Recovery Menunggu Persetujuan
                        </CardTitle>

                        <CardDescription>
                            Approval Recovery merupakan
                            satu-satunya jalur untuk
                            mengembalikan Commitment
                            YELLOW menjadi GREEN.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                        {recoveries.length === 0 ? (
                            <EmptyState />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1200px] text-left text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Forecast
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Produsen
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Version
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Confidence
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Alasan Recovery
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Diajukan
                                            </th>

                                            <th className="px-4 py-3 text-right font-medium">
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {recoveries.map(
                                            (
                                                recovery,
                                            ) => (
                                                <tr
                                                    key={
                                                        recovery.id
                                                    }
                                                >
                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                recovery
                                                                    .forecast
                                                                    .forecast_code
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                recovery
                                                                    .forecast
                                                                    .commodity_name
                                                            }{" "}
                                                            •{" "}
                                                            {
                                                                recovery
                                                                    .forecast
                                                                    .sppg_name
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                recovery
                                                                    .producer
                                                                    .name
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                recovery
                                                                    .producer
                                                                    .producer_code
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            Version{" "}
                                                            {
                                                                recovery
                                                                    .commitment_version
                                                                    .version_no
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {formatRange(
                                                                recovery
                                                                    .commitment_version,
                                                            )}
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-400">
                                                            <TriangleAlert className="size-3.5" />
                                                            Berisiko
                                                        </span>
                                                    </td>

                                                    <td className="max-w-sm px-4 py-4">
                                                        <p className="line-clamp-2 text-sm leading-6 text-foreground">
                                                            {
                                                                recovery.recovery_reason
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                recovery
                                                                    .requested_by
                                                                    .name
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {formatDateTime(
                                                                recovery.requested_at,
                                                            )}
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <div className="flex justify-end">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                onClick={() =>
                                                                    router.visit(
                                                                        `/kdkmp/manager/recoveries/${recovery.id}`,
                                                                    )
                                                                }
                                                            >
                                                                <Eye data-icon="inline-start" />
                                                                Review
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
            </KdkmpLayout>
        </>
    );
}

function EmptyState() {
    return (
        <div className="flex min-h-64 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <RefreshCcw className="size-5" />
            </div>

            <h2 className="mt-4 font-semibold text-foreground">
                Tidak ada Recovery tertunda
            </h2>

            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                Recovery Request dari Operator akan
                muncul di sini ketika Commitment
                berstatus YELLOW.
            </p>
        </div>
    );
}

function formatRange(version) {
    return `${formatNumber(
        version.min_volume,
    )}–${formatNumber(
        version.max_volume,
    )} ${version.unit_symbol ?? ""}`;
}

function formatNumber(value) {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return value ?? "—";
    }

    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 6,
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