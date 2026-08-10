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
    CircleCheck,
    CircleHelp,
    CircleX,
    Clock3,
    Eye,
    Handshake,
    Plus,
    TriangleAlert,
} from "lucide-react";

export default function Index({
    commitments,
    canCreate,
}) {
    return (
        <>
            <Head title="Komitmen Pasokan — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Komitmen Pasokan"
                pageDescription="Kelola draft, approval state, revision, dan confidence commitment KDKMP."
                headerActions={
                    canCreate ? (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() =>
                                router.visit(
                                    "/kdkmp/commitments/create",
                                )
                            }
                        >
                            <Plus data-icon="inline-start" />
                            Buat Commitment
                        </Button>
                    ) : null
                }
            >
                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>
                            Daftar Komitmen Pasokan
                        </CardTitle>

                        <CardDescription>
                            Approval state dan Confidence
                            Pasokan ditampilkan terpisah.
                            Commitment baru hanya menjadi
                            GREEN setelah approval pertama
                            oleh Manager.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                        {commitments.length === 0 ? (
                            <EmptyState
                                canCreate={
                                    canCreate
                                }
                            />
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
                                                Range Pasokan
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Workflow
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Confidence
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Verifikasi
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
                                            ) => {
                                                const displayVersion =
                                                    commitment.active_version ??
                                                    commitment.latest_version;

                                                return (
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
                                                                        .forecast
                                                                        .sppg_name
                                                                }
                                                            </p>

                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {
                                                                    commitment
                                                                        .commodity
                                                                        .name
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

                                                        <td className="px-4 py-4">
                                                            {displayVersion ? (
                                                                <>
                                                                    <p className="font-medium text-foreground">
                                                                        {formatRange(
                                                                            displayVersion,
                                                                        )}
                                                                    </p>

                                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                                        {commitment.active_version
                                                                            ? `Active Version ${commitment.active_version.version_no}`
                                                                            : `Draft Version ${commitment.latest_version.version_no}`}
                                                                    </p>
                                                                </>
                                                            ) : (
                                                                "—"
                                                            )}
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <WorkflowBadge
                                                                state={
                                                                    commitment.workflow_state
                                                                }
                                                            />
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

                                                        <td className="px-4 py-4 text-muted-foreground">
                                                            {commitment.last_confidence_verified_at
                                                                ? formatDateTime(
                                                                      commitment.last_confidence_verified_at,
                                                                  )
                                                                : "Belum diverifikasi"}
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
                                                );
                                            },
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

function EmptyState({ canCreate }) {
    return (
        <div className="flex min-h-64 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Handshake className="size-5" />
            </div>

            <h2 className="mt-4 font-semibold text-foreground">
                Belum ada Komitmen Pasokan
            </h2>

            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                Buat Draft Commitment dari Forecast
                PUBLISHED untuk mulai menyiapkan pasokan
                internal KDKMP.
            </p>

            {canCreate && (
                <Button
                    type="button"
                    className="mt-5"
                    onClick={() =>
                        router.visit(
                            "/kdkmp/commitments/create",
                        )
                    }
                >
                    <Plus data-icon="inline-start" />
                    Buat Commitment
                </Button>
            )}
        </div>
    );
}

function WorkflowBadge({ state }) {
    if (!state) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                <CircleHelp className="size-3.5" />
                Tidak diketahui
            </span>
        );
    }

    const approved =
        state.value === "APPROVED";

    const rejected =
        state.value === "REJECTED" ||
        state.value === "REVISION_REJECTED";

    const Icon = approved
        ? CircleCheck
        : rejected
          ? CircleX
          : Clock3;

    return (
        <span
            className={[
                "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
                approved
                    ? "bg-primary/10 text-primary"
                    : rejected
                      ? "bg-muted text-muted-foreground"
                      : "bg-muted text-foreground",
            ].join(" ")}
        >
            <Icon className="size-3.5" />
            {state.label}
        </span>
    );
}

function ConfidenceBadge({
    value,
    label,
}) {
    const config = {
        GREEN: {
            icon: CircleCheck,
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