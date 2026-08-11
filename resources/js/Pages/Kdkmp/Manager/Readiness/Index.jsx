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
    FileCheck2,
    Truck,
} from "lucide-react";

export default function Index({
    checklists,
}) {
    const logisticsCount =
        checklists.filter(
            (checklist) =>
                checklist.readiness_type ===
                "LOGISTICS",
        ).length;

    const documentCount =
        checklists.filter(
            (checklist) =>
                checklist.readiness_type ===
                "DOCUMENT",
        ).length;

    return (
        <>
            <Head title="Readiness Approval — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Readiness Approval"
                pageDescription="Review Logistics dan Document Readiness yang telah diajukan Operator."
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Menunggu Review"
                            value={
                                checklists.length
                            }
                            icon={
                                ClipboardCheck
                            }
                        />

                        <SummaryCard
                            label="Logistics"
                            value={
                                logisticsCount
                            }
                            icon={Truck}
                        />

                        <SummaryCard
                            label="Document"
                            value={
                                documentCount
                            }
                            icon={FileCheck2}
                        />
                    </div>

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Readiness Menunggu Persetujuan
                            </CardTitle>

                            <CardDescription>
                                Manager melakukan
                                review terhadap payload
                                yang telah dikunci
                                Operator. Payload tidak
                                dapat diedit dari queue
                                ini.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="p-0">
                            {checklists.length ===
                            0 ? (
                                <EmptyState />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[1100px] text-left text-sm">
                                        <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Forecast
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Jenis
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Version
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
                                            {checklists.map(
                                                (
                                                    checklist,
                                                ) => (
                                                    <tr
                                                        key={
                                                            checklist.id
                                                        }
                                                    >
                                                        <td className="px-4 py-4">
                                                            <p className="font-medium text-foreground">
                                                                {
                                                                    checklist
                                                                        .forecast
                                                                        .forecast_code
                                                                }
                                                            </p>

                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {
                                                                    checklist
                                                                        .forecast
                                                                        .commodity_name
                                                                }
                                                            </p>

                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {
                                                                    checklist
                                                                        .forecast
                                                                        .sppg_name
                                                                }
                                                            </p>
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <ReadinessTypeBadge
                                                                value={
                                                                    checklist.readiness_type
                                                                }
                                                            />
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <p className="font-medium text-foreground">
                                                                Version{" "}
                                                                {
                                                                    checklist.version_no
                                                                }
                                                            </p>

                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                Forecast
                                                                v
                                                                {
                                                                    checklist.forecast_version
                                                                }
                                                            </p>
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            {checklist.submitted_by ? (
                                                                <p className="font-medium text-foreground">
                                                                    {
                                                                        checklist
                                                                            .submitted_by
                                                                            .name
                                                                    }
                                                                </p>
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>

                                                        <td className="px-4 py-4 text-muted-foreground">
                                                            {formatDateTime(
                                                                checklist.submitted_at,
                                                            )}
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <div className="flex justify-end">
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        router.visit(
                                                                            `/kdkmp/manager/readiness/${checklist.id}`,
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
                </div>
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
                Tidak ada Readiness tertunda
            </h2>

            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                Logistics atau Document
                Readiness yang telah disubmit
                Operator akan muncul pada queue
                ini.
            </p>
        </div>
    );
}

function SummaryCard({
    label,
    value,
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

                        <p className="mt-2 text-2xl font-semibold text-foreground">
                            {value}
                        </p>
                    </div>

                    <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon className="size-4" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function ReadinessTypeBadge({ value }) {
    const Icon =
        value === "LOGISTICS"
            ? Truck
            : FileCheck2;

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-foreground">
            <Icon className="size-3.5" />

            {value === "LOGISTICS"
                ? "Logistics"
                : "Document"}
        </span>
    );
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