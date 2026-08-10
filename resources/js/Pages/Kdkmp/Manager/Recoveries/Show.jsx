import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import KdkmpLayout from "@/Layouts/KdkmpLayout";
import {
    Head,
    router,
    useForm,
} from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    CircleX,
    LoaderCircle,
    TriangleAlert,
} from "lucide-react";
import { useState } from "react";

const textareaClassName =
    "w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15";

function FieldError({ message }) {
    if (!message) {
        return null;
    }

    return (
        <p className="mt-1.5 text-sm text-destructive">
            {message}
        </p>
    );
}

export default function Show({
    review,
    can,
}) {
    const [activeAction, setActiveAction] =
        useState(null);

    const approveForm = useForm({
        review_reason: "",
    });

    const rejectForm = useForm({
        review_reason: "",
    });

    const {
        recovery,
        commitment,
        requested_version: requestedVersion,
        confidence_events: confidenceEvents,
    } = review;

    const approve = (event) => {
        event.preventDefault();

        approveForm.post(
            `/kdkmp/manager/recoveries/${recovery.id}/approve`,
            {
                preserveScroll: true,
            },
        );
    };

    const reject = (event) => {
        event.preventDefault();

        rejectForm.post(
            `/kdkmp/manager/recoveries/${recovery.id}/reject`,
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head
                title={`Recovery #${recovery.id} — SiagaPasok`}
            />

            <KdkmpLayout
                pageTitle={`Recovery Review #${recovery.id}`}
                pageDescription={`${commitment.forecast.forecast_code} • ${commitment.producer.name}`}
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                "/kdkmp/manager/recoveries",
                            )
                        }
                    >
                        <ArrowLeft data-icon="inline-start" />
                        Recovery Review
                    </Button>
                }
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Recovery State"
                            value={
                                recovery.status_label
                            }
                        />

                        <SummaryCard
                            label="Confidence Saat Ini"
                            value={
                                commitment.current_confidence_label ??
                                commitment.current_confidence ??
                                "—"
                            }
                        />

                        <SummaryCard
                            label="Terakhir Diverifikasi"
                            value={formatDateTime(
                                commitment.last_confidence_verified_at,
                            )}
                        />
                    </div>

                    {!requestedVersion.is_current_active_version && (
                        <Card className="border-amber-500/30">
                            <CardContent>
                                <div className="flex items-start gap-3">
                                    <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-400" />

                                    <div>
                                        <p className="font-medium text-foreground">
                                            Active Version
                                            telah berubah
                                        </p>

                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            Recovery ini
                                            dibuat terhadap
                                            Version{" "}
                                            {
                                                requestedVersion.version_no
                                            }
                                            , tetapi version
                                            tersebut tidak
                                            lagi menjadi
                                            active version.
                                            Backend akan
                                            menolak approval
                                            Recovery lama.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Recovery Request
                            </CardTitle>

                            <CardDescription>
                                Evidence operasional yang
                                diajukan Operator untuk
                                pemulihan confidence.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-5">
                            <DetailItem
                                label="Diajukan Oleh"
                                value={
                                    recovery.requested_by
                                        .name
                                }
                                description={formatDateTime(
                                    recovery.requested_at,
                                )}
                            />

                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Alasan / Evidence
                                </p>

                                <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                                    {
                                        recovery.recovery_reason
                                    }
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Commitment Context
                            </CardTitle>

                            <CardDescription>
                                Kondisi supply yang akan
                                kembali menjadi GREEN jika
                                Recovery disetujui.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <div className="grid gap-5 md:grid-cols-2">
                                <DetailItem
                                    label="Forecast"
                                    value={
                                        commitment.forecast
                                            .forecast_code
                                    }
                                    description={`${commitment.forecast.commodity_name} • ${commitment.forecast.sppg_name}`}
                                />

                                <DetailItem
                                    label="Produsen"
                                    value={
                                        commitment.producer
                                            .name
                                    }
                                    description={
                                        commitment.producer
                                            .producer_code
                                    }
                                />

                                <DetailItem
                                    label="Periode Forecast"
                                    value={formatDateTime(
                                        commitment.forecast
                                            .required_start_at,
                                    )}
                                    description={`s.d. ${formatDateTime(
                                        commitment.forecast
                                            .required_end_at,
                                    )}`}
                                />

                                <DetailItem
                                    label="Status Produsen"
                                    value={
                                        commitment.producer
                                            .is_active
                                            ? "Aktif"
                                            : "Nonaktif"
                                    }
                                />
                            </div>

                            {commitment.expected_harvest && (
                                <>
                                    <div className="border-t border-border" />

                                    <DetailItem
                                        label="Expected Harvest"
                                        value={`${formatNumber(
                                            commitment
                                                .expected_harvest
                                                .expected_min_volume,
                                        )}–${formatNumber(
                                            commitment
                                                .expected_harvest
                                                .expected_max_volume,
                                        )} ${
                                            commitment
                                                .expected_harvest
                                                .unit_symbol
                                        }`}
                                    />
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>
                                        Version yang
                                        Diverifikasi
                                    </CardTitle>

                                    <CardDescription>
                                        Recovery harus tetap
                                        menunjuk active
                                        version yang sama.
                                    </CardDescription>
                                </div>

                                {requestedVersion.is_current_active_version ? (
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                                        <CheckCircle2 className="size-3.5" />
                                        Active Version
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                        <CircleX className="size-3.5" />
                                        Bukan Active Version
                                    </span>
                                )}
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <div className="grid gap-5 md:grid-cols-2">
                                <DetailItem
                                    label="Version"
                                    value={`Version ${requestedVersion.version_no}`}
                                />

                                <DetailItem
                                    label="Range"
                                    value={`${formatNumber(
                                        requestedVersion.min_volume,
                                    )}–${formatNumber(
                                        requestedVersion.max_volume,
                                    )} ${
                                        requestedVersion.unit_symbol
                                    }`}
                                />

                                <DetailItem
                                    label="Window Ketersediaan"
                                    value={formatDateTime(
                                        requestedVersion.availability_start_at,
                                    )}
                                    description={`s.d. ${formatDateTime(
                                        requestedVersion.availability_end_at,
                                    )}`}
                                />
                            </div>

                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Catatan
                                </p>

                                <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                                    {requestedVersion.notes ||
                                        "Tidak ada catatan."}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Riwayat Confidence
                            </CardTitle>

                            <CardDescription>
                                Gunakan riwayat perubahan
                                kondisi sebagai konteks
                                review Recovery.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            {confidenceEvents.length ===
                            0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada riwayat
                                    confidence.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {confidenceEvents.map(
                                        (event) => (
                                            <div
                                                key={
                                                    event.id
                                                }
                                                className="flex gap-3 rounded-xl border border-border p-4"
                                            >
                                                <Activity className="mt-0.5 size-4 shrink-0 text-muted-foreground" />

                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <ConfidenceBadge
                                                            value={
                                                                event.to_confidence
                                                            }
                                                        />

                                                        <span className="text-xs text-muted-foreground">
                                                            {event.actor_name ??
                                                                event.source}
                                                        </span>
                                                    </div>

                                                    <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                                                        {event.reason_note ||
                                                            "Tidak ada catatan."}
                                                    </p>

                                                    <p className="mt-2 text-xs text-muted-foreground">
                                                        {formatDateTime(
                                                            event.occurred_at,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {(can.approve ||
                        can.reject) && (
                        <Card>
                            <CardHeader className="border-b">
                                <CardTitle>
                                    Keputusan Recovery
                                </CardTitle>

                                <CardDescription>
                                    Approve mengembalikan
                                    Commitment dari YELLOW
                                    menjadi GREEN. Reject
                                    mempertahankan YELLOW.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {can.approve && (
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "approve"
                                                        ? null
                                                        : "approve",
                                                )
                                            }
                                        >
                                            <CheckCircle2 data-icon="inline-start" />
                                            Approve Recovery
                                        </Button>
                                    )}

                                    {can.reject && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "reject"
                                                        ? null
                                                        : "reject",
                                                )
                                            }
                                        >
                                            <CircleX data-icon="inline-start" />
                                            Reject Recovery
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {activeAction === "approve" && (
                        <ApproveRecoveryPanel
                            form={
                                approveForm
                            }
                            onSubmit={
                                approve
                            }
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}

                    {activeAction === "reject" && (
                        <RejectRecoveryPanel
                            form={
                                rejectForm
                            }
                            onSubmit={
                                reject
                            }
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}
                </div>
            </KdkmpLayout>
        </>
    );
}

function ApproveRecoveryPanel({
    form,
    onSubmit,
    onCancel,
}) {
    return (
        <form onSubmit={onSubmit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        Approve Recovery?
                    </CardTitle>

                    <CardDescription>
                        Setelah approval berhasil,
                        Confidence menjadi GREEN dan
                        freshness verification dimulai
                        kembali dari waktu approval ini.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <label
                        htmlFor="approve_review_reason"
                        className="mb-2 block text-sm font-medium text-foreground"
                    >
                        Catatan Review
                    </label>

                    <textarea
                        id="approve_review_reason"
                        rows={4}
                        value={
                            form.data
                                .review_reason
                        }
                        onChange={(event) =>
                            form.setData(
                                "review_reason",
                                event.target.value,
                            )
                        }
                        className={
                            textareaClassName
                        }
                        placeholder="Opsional."
                    />

                    <FieldError
                        message={
                            form.errors
                                .review_reason
                        }
                    />

                    <FieldError
                        message={
                            form.errors
                                .current_confidence
                        }
                    />

                    <FieldError
                        message={
                            form.errors
                                .commitment_version_id
                        }
                    />

                    <FieldError
                        message={
                            form.errors.recovery
                        }
                    />
                </CardContent>

                <CardFooter className="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                    >
                        Batal
                    </Button>

                    <Button
                        type="submit"
                        disabled={form.processing}
                    >
                        {form.processing && (
                            <LoaderCircle
                                data-icon="inline-start"
                                className="animate-spin"
                            />
                        )}

                        <CheckCircle2 data-icon="inline-start" />
                        Approve Recovery
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

function RejectRecoveryPanel({
    form,
    onSubmit,
    onCancel,
}) {
    return (
        <form onSubmit={onSubmit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        Reject Recovery
                    </CardTitle>

                    <CardDescription>
                        Commitment akan tetap YELLOW.
                        Alasan penolakan wajib dicatat.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <label
                        htmlFor="reject_review_reason"
                        className="mb-2 block text-sm font-medium text-foreground"
                    >
                        Alasan Penolakan
                    </label>

                    <textarea
                        id="reject_review_reason"
                        rows={5}
                        value={
                            form.data
                                .review_reason
                        }
                        onChange={(event) =>
                            form.setData(
                                "review_reason",
                                event.target.value,
                            )
                        }
                        className={
                            textareaClassName
                        }
                        placeholder="Jelaskan mengapa kondisi supply belum cukup untuk kembali GREEN."
                        required
                    />

                    <FieldError
                        message={
                            form.errors
                                .review_reason
                        }
                    />
                </CardContent>

                <CardFooter className="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                    >
                        Batal
                    </Button>

                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={form.processing}
                    >
                        {form.processing && (
                            <LoaderCircle
                                data-icon="inline-start"
                                className="animate-spin"
                            />
                        )}

                        <CircleX data-icon="inline-start" />
                        Reject Recovery
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

function SummaryCard({ label, value }) {
    return (
        <Card>
            <CardContent>
                <p className="text-sm text-muted-foreground">
                    {label}
                </p>

                <p className="mt-2 text-lg font-semibold text-foreground">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

function DetailItem({
    label,
    value,
    description,
}) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>

            <p className="mt-1 font-medium text-foreground">
                {value}
            </p>

            {description && (
                <p className="mt-0.5 text-xs text-muted-foreground">
                    {description}
                </p>
            )}
        </div>
    );
}

function ConfidenceBadge({ value }) {
    const config = {
        GREEN: {
            label: "Aman",
            className:
                "bg-green-500/10 text-green-700 dark:text-green-400",
        },

        YELLOW: {
            label: "Berisiko",
            className:
                "bg-amber-500/10 text-amber-700 dark:text-amber-400",
        },

        RED: {
            label: "Kritis",
            className:
                "bg-red-500/10 text-red-700 dark:text-red-400",
        },
    };

    const selected =
        config[value] ?? {
            label: value ?? "—",
            className:
                "bg-muted text-muted-foreground",
        };

    return (
        <span
            className={[
                "inline-flex rounded-full px-2.5 py-1 text-xs font-medium",
                selected.className,
            ].join(" ")}
        >
            {selected.label}
        </span>
    );
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