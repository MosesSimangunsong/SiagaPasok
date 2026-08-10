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
    ClipboardCheck,
    Clock3,
    Eye,
} from "lucide-react";

export default function Index({ versions }) {
    return (
        <>
            <Head title="Approval Queue — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Approval Queue"
                pageDescription="Review komitmen pasokan yang menunggu keputusan Manager."
            >
                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>
                            Commitment Menunggu Persetujuan
                        </CardTitle>

                        <CardDescription>
                            Manager hanya melakukan review,
                            Approve, atau Reject. Payload
                            Operator tidak dapat diedit dari
                            halaman ini.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                        {versions.length === 0 ? (
                            <EmptyState />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1250px] text-left text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Forecast
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Produsen
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Tipe Review
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Range
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Confidence
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Diajukan Oleh
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Waktu Submit
                                            </th>

                                            <th className="px-4 py-3 text-right font-medium">
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {versions.map(
                                            (version) => (
                                                <tr
                                                    key={
                                                        version.id
                                                    }
                                                >
                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                version
                                                                    .forecast
                                                                    .forecast_code
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                version
                                                                    .forecast
                                                                    .commodity_name
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                version
                                                                    .forecast
                                                                    .sppg_name
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                version
                                                                    .producer
                                                                    .name
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                version
                                                                    .producer
                                                                    .producer_code
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <ReviewTypeBadge
                                                            version={
                                                                version
                                                            }
                                                        />
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {formatRange(
                                                                version,
                                                            )}
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            Version{" "}
                                                            {
                                                                version.version_no
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <ConfidenceContext
                                                            value={
                                                                version.current_confidence
                                                            }
                                                        />
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        {version.submitted_by ? (
                                                            <p className="font-medium text-foreground">
                                                                {
                                                                    version
                                                                        .submitted_by
                                                                        .name
                                                                }
                                                            </p>
                                                        ) : (
                                                            "—"
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {formatDateTime(
                                                            version.submitted_at,
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <div className="flex justify-end">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                onClick={() =>
                                                                    router.visit(
                                                                        `/kdkmp/manager/approvals/${version.commitment_id}/versions/${version.id}`,
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
                <ClipboardCheck className="size-5" />
            </div>

            <h2 className="mt-4 font-semibold text-foreground">
                Tidak ada approval tertunda
            </h2>

            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                Commitment yang telah disubmit Operator
                akan muncul di queue ini.
            </p>
        </div>
    );
}

function ReviewTypeBadge({ version }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-foreground">
            <Clock3 className="size-3.5" />
            {version.review_type_label}
        </span>
    );
}

function ConfidenceContext({ value }) {
    if (!value) {
        return (
            <span className="text-sm text-muted-foreground">
                Belum ada
            </span>
        );
    }

    const labels = {
        GREEN: "Aman",
        YELLOW: "Berisiko",
        RED: "Kritis",
    };

    return (
        <span className="text-sm font-medium text-foreground">
            {labels[value] ?? value}
        </span>
    );
}

function formatRange(version) {
    const precision =
        version.unit?.decimal_precision ?? 2;

    return `${formatNumber(
        version.min_volume,
        precision,
    )}–${formatNumber(
        version.max_volume,
        precision,
    )} ${version.unit?.symbol ?? ""}`;
}

function formatNumber(value, precision = 2) {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return value ?? "—";
    }

    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: precision,
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