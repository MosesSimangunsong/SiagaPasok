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
    ClipboardCheck,
    Clock3,
    Eye,
    FileClock,
    Files,
} from "lucide-react";

export default function Index({ checklists }) {
    const pendingCount =
        checklists.filter(
            (checklist) =>
                checklist.status ===
                "PENDING_APPROVAL",
        ).length;

    const approvedCount =
        checklists.filter(
            (checklist) =>
                checklist.status === "APPROVED",
        ).length;

    return (
        <>
            <Head title="Readiness — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Readiness Operasional"
                pageDescription="Persiapkan dan pantau Logistics serta Document Readiness untuk kontribusi pasokan KDKMP."
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                "/kdkmp/forecasts",
                            )
                        }
                    >
                        <ClipboardCheck data-icon="inline-start" />
                        Forecast Aktif
                    </Button>
                }
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Current Checklist"
                            value={checklists.length}
                            icon={Files}
                        />

                        <SummaryCard
                            label="Menunggu Persetujuan"
                            value={pendingCount}
                            icon={Clock3}
                        />

                        <SummaryCard
                            label="Disetujui"
                            value={approvedCount}
                            icon={CheckCircle2}
                        />
                    </div>

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Current Readiness
                            </CardTitle>

                            <CardDescription>
                                Hanya current version yang
                                digunakan untuk menentukan
                                kesiapan operasional. Version
                                historis tetap dipertahankan
                                untuk audit.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="p-0">
                            {checklists.length === 0 ? (
                                <EmptyState />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[1050px] text-left text-sm">
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
                                                    Status
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
                                            {checklists.map(
                                                (checklist) => (
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
                                                                {" • "}
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
                                                                Forecast v
                                                                {
                                                                    checklist.forecast_version
                                                                }
                                                            </p>
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <ApprovalBadge
                                                                value={
                                                                    checklist.status
                                                                }
                                                            />
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
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        router.visit(
                                                                            `/kdkmp/readiness/${checklist.id}`,
                                                                        )
                                                                    }
                                                                >
                                                                    <Eye data-icon="inline-start" />
                                                                    Buka
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
        <div className="flex min-h-72 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <FileClock className="size-5" />
            </div>

            <h2 className="mt-4 font-semibold text-foreground">
                Belum ada readiness checklist
            </h2>

            <p className="mt-1 max-w-lg text-sm leading-6 text-muted-foreground">
                Readiness disiapkan untuk Forecast
                ketika KDKMP menjadi contributor efektif.
                Buka Forecast Aktif untuk memulai
                Logistics atau Document Readiness.
            </p>

            <Button
                type="button"
                className="mt-5"
                onClick={() =>
                    router.visit("/kdkmp/forecasts")
                }
            >
                <ClipboardCheck data-icon="inline-start" />
                Buka Forecast Aktif
            </Button>
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
    return (
        <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-foreground">
            {value === "LOGISTICS"
                ? "Logistics"
                : value === "DOCUMENT"
                  ? "Document"
                  : value}
        </span>
    );
}

function ApprovalBadge({ value }) {
    const config = {
        DRAFT: {
            icon: FileClock,
            label: "Draft",
            className:
                "bg-muted text-muted-foreground",
        },

        PENDING_APPROVAL: {
            icon: Clock3,
            label: "Menunggu Persetujuan",
            className:
                "bg-primary/10 text-primary",
        },

        APPROVED: {
            icon: CheckCircle2,
            label: "Disetujui",
            className:
                "bg-primary/10 text-primary",
        },

        REJECTED: {
            icon: FileClock,
            label: "Ditolak",
            className:
                "bg-destructive/10 text-destructive",
        },
    };

    const selected =
        config[value] ?? config.DRAFT;

    const Icon = selected.icon;

    return (
        <span
            className={[
                "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
                selected.className,
            ].join(" ")}
        >
            <Icon className="size-3.5" />
            {selected.label}
        </span>
    );
}

function formatDateTime(value) {
    if (!value) {
        return "Belum diajukan";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}