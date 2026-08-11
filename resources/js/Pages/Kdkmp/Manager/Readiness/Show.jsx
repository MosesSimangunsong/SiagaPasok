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
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    CircleX,
    Clock3,
    FileCheck2,
    LoaderCircle,
    ShieldCheck,
    Truck,
} from "lucide-react";
import { useState } from "react";

const textareaClassName =
    "w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15";

export default function Show({
    review,
    can,
}) {
    const [activeAction, setActiveAction] =
        useState(null);

    const approveForm = useForm({});

    const rejectForm = useForm({
        review_reason: "",
    });

    const approve = () => {
        approveForm.post(
            `/kdkmp/manager/readiness/${review.id}/approve`,
            {
                preserveScroll: true,
            },
        );
    };

    const reject = (event) => {
        event.preventDefault();

        rejectForm.post(
            `/kdkmp/manager/readiness/${review.id}/reject`,
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head
                title={`${readinessTypeLabel(
                    review.readiness_type,
                )} Readiness Review — SiagaPasok`}
            />

            <KdkmpLayout
                pageTitle={`${readinessTypeLabel(
                    review.readiness_type,
                )} Readiness Review`}
                pageDescription={`${review.forecast.forecast_code} • Checklist Version ${review.version_no}`}
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                "/kdkmp/manager/readiness",
                            )
                        }
                    >
                        <ArrowLeft data-icon="inline-start" />
                        Approval Queue
                    </Button>
                }
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Jenis"
                            value={readinessTypeLabel(
                                review.readiness_type,
                            )}
                        />

                        <SummaryCard
                            label="Checklist"
                            value={`Version ${review.version_no}`}
                        />

                        <SummaryCard
                            label="Status"
                            value={approvalLabel(
                                review.status,
                            )}
                        />
                    </div>

                    <ReadOnlyNotice />

                    {review.review_reason && (
                        <Card className="border-destructive/25">
                            <CardContent>
                                <div className="flex items-start gap-3">
                                    <CircleX className="mt-0.5 size-5 shrink-0 text-destructive" />

                                    <div>
                                        <p className="font-medium text-foreground">
                                            Catatan Review
                                        </p>

                                        <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-muted-foreground">
                                            {
                                                review.review_reason
                                            }
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Konteks Pengajuan
                            </CardTitle>

                            <CardDescription>
                                Pastikan Forecast,
                                contributor, version,
                                dan submission context
                                sesuai sebelum mengambil
                                keputusan.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                <DetailItem
                                    label="Forecast"
                                    value={
                                        review.forecast
                                            .forecast_code
                                    }
                                    description={
                                        review.forecast
                                            .sppg_name
                                    }
                                />

                                <DetailItem
                                    label="Komoditas"
                                    value={
                                        review.forecast
                                            .commodity_name
                                    }
                                />

                                <DetailItem
                                    label="Target"
                                    value={`${formatNumber(
                                        review.forecast
                                            .target_volume,
                                    )} ${
                                        review.forecast
                                            .unit_symbol
                                    }`}
                                />

                                <DetailItem
                                    label="Organisasi KDKMP"
                                    value={
                                        review.organization
                                            .name
                                    }
                                    description={
                                        review.organization
                                            .code
                                    }
                                />

                                <DetailItem
                                    label="Forecast Version"
                                    value={`Current v${review.forecast.version}`}
                                    description={`Checklist snapshot v${review.forecast_version}`}
                                />

                                <DetailItem
                                    label="Periode"
                                    value={formatDateTime(
                                        review.forecast
                                            .required_start_at,
                                    )}
                                    description={`s.d. ${formatDateTime(
                                        review.forecast
                                            .required_end_at,
                                    )}`}
                                />
                            </div>

                            <div className="mt-6 border-t border-border pt-6">
                                <div className="grid gap-5 md:grid-cols-3">
                                    <DetailItem
                                        label="Maker"
                                        value={
                                            review
                                                .prepared_by
                                                ?.name ??
                                            "—"
                                        }
                                    />

                                    <DetailItem
                                        label="Submitter"
                                        value={
                                            review
                                                .submitted_by
                                                ?.name ??
                                            "—"
                                        }
                                    />

                                    <DetailItem
                                        label="Waktu Submit"
                                        value={formatDateTime(
                                            review.submitted_at,
                                        )}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>
                                        Payload Readiness
                                    </CardTitle>

                                    <CardDescription>
                                        Payload ini
                                        read-only pada
                                        Manager review.
                                    </CardDescription>
                                </div>

                                <ReadinessTypeBadge
                                    value={
                                        review.readiness_type
                                    }
                                />
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            {review.items.map(
                                (item) => (
                                    <ReviewItem
                                        key={item.id}
                                        item={item}
                                    />
                                ),
                            )}
                        </CardContent>
                    </Card>

                    <DerivedTruthCard
                        review={review}
                    />

                    {(can.approve ||
                        can.reject) && (
                        <Card>
                            <CardHeader className="border-b">
                                <CardTitle>
                                    Keputusan Manager
                                </CardTitle>

                                <CardDescription>
                                    Approval atau
                                    rejection merupakan
                                    explicit business
                                    command. Backend akan
                                    memvalidasi ulang
                                    contributor, Forecast,
                                    checklist, dan dokumen
                                    sebelum keputusan
                                    diterapkan.
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
                                            <ShieldCheck data-icon="inline-start" />
                                            Approve
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
                                            Reject
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {activeAction ===
                        "approve" && (
                        <ApprovePanel
                            form={
                                approveForm
                            }
                            onConfirm={
                                approve
                            }
                            onCancel={() =>
                                setActiveAction(
                                    null,
                                )
                            }
                        />
                    )}

                    {activeAction ===
                        "reject" && (
                        <RejectPanel
                            form={
                                rejectForm
                            }
                            onSubmit={
                                reject
                            }
                            onCancel={() =>
                                setActiveAction(
                                    null,
                                )
                            }
                        />
                    )}
                </div>
            </KdkmpLayout>
        </>
    );
}

function ReadOnlyNotice() {
    return (
        <Card>
            <CardContent>
                <div className="flex items-start gap-3">
                    <ShieldCheck className="mt-0.5 size-5 shrink-0 text-primary" />

                    <div>
                        <p className="font-medium text-foreground">
                            Review read-only
                        </p>

                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                            Manager dapat membaca
                            requirement dan evidence,
                            tetapi tidak dapat mengubah
                            checkbox, catatan, Document
                            Record, atau payload Operator.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function ReviewItem({ item }) {
    return (
        <div className="rounded-xl border border-border p-4">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-medium text-foreground">
                            {
                                item.requirement
                                    .label
                            }
                        </p>

                        {item.is_required && (
                            <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                                Wajib
                            </span>
                        )}
                    </div>

                    <p className="mt-1 text-xs text-muted-foreground">
                        {
                            item.requirement
                                .code
                        }
                        {" • "}
                        {scopeLabel(
                            item.requirement
                                .scope,
                        )}
                    </p>
                </div>

                <SatisfiedBadge
                    value={
                        item.is_satisfied
                    }
                />
            </div>

            {item.note && (
                <div className="mt-4 border-t border-border pt-4">
                    <DetailItem
                        label="Catatan Operator"
                        value={item.note}
                    />
                </div>
            )}

            {item.document_record && (
                <div className="mt-4 rounded-lg bg-muted/35 p-4">
                    <div className="flex items-start gap-3">
                        <FileCheck2 className="mt-0.5 size-4 shrink-0 text-muted-foreground" />

                        <div>
                            <p className="font-medium text-foreground">
                                {
                                    item
                                        .document_record
                                        .document_name
                                }
                            </p>

                            <p className="mt-1 text-xs text-muted-foreground">
                                {item
                                    .document_record
                                    .reference_number ||
                                    "Tanpa nomor referensi"}
                            </p>

                            <p className="mt-1 text-xs text-muted-foreground">
                                {
                                    item
                                        .document_record
                                        .status
                                }
                                {" • Current rev "}
                                {
                                    item
                                        .document_record
                                        .revision_no
                                }
                            </p>

                            <p className="mt-1 text-xs text-muted-foreground">
                                Berlaku{" "}
                                {formatDateTime(
                                    item
                                        .document_record
                                        .valid_from,
                                )}
                                {" s.d. "}
                                {formatDateTime(
                                    item
                                        .document_record
                                        .expires_at,
                                )}
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function DerivedTruthCard({ review }) {
    const relevantReady =
        review.readiness_type ===
        "LOGISTICS"
            ? review.current_readiness
                  .logistics_ready
            : review.current_readiness
                  .document_ready;

    return (
        <Card
            className={
                relevantReady
                    ? "border-primary/25"
                    : "border-amber-500/30"
            }
        >
            <CardContent>
                <div className="flex items-start gap-3">
                    {relevantReady ? (
                        <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-primary" />
                    ) : (
                        <Clock3 className="mt-0.5 size-5 shrink-0 text-amber-700" />
                    )}

                    <div>
                        <p className="font-medium text-foreground">
                            Current derived
                            readiness:{" "}
                            {relevantReady
                                ? "READY"
                                : "NOT READY"}
                        </p>

                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                            Checklist yang masih
                            PENDING tetap menghasilkan
                            NOT READY sampai approval
                            berhasil. Approval command
                            akan melakukan revalidasi
                            backend sebelum readiness
                            dapat berubah.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function ApprovePanel({
    form,
    onConfirm,
    onCancel,
}) {
    return (
        <Card className="border-primary/25">
            <CardHeader className="border-b">
                <CardTitle>
                    Approve Readiness?
                </CardTitle>

                <CardDescription>
                    Approval tidak memaksa status
                    Ready. Backend tetap mengevaluasi
                    contributor, Forecast version,
                    required item, serta current
                    Document Record state.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <RequestErrors
                    errors={form.errors}
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
                    type="button"
                    disabled={
                        form.processing
                    }
                    onClick={onConfirm}
                >
                    {form.processing ? (
                        <LoaderCircle
                            data-icon="inline-start"
                            className="animate-spin"
                        />
                    ) : (
                        <ShieldCheck data-icon="inline-start" />
                    )}

                    Konfirmasi Approve
                </Button>
            </CardFooter>
        </Card>
    );
}

function RejectPanel({
    form,
    onSubmit,
    onCancel,
}) {
    return (
        <form onSubmit={onSubmit}>
            <Card className="border-destructive/25">
                <CardHeader className="border-b">
                    <CardTitle>
                        Reject Readiness
                    </CardTitle>

                    <CardDescription>
                        Alasan rejection wajib
                        dicatat. Operator harus membuat
                        revision baru sebelum payload
                        dapat diajukan kembali.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <label
                        htmlFor="review_reason"
                        className="mb-2 block text-sm font-medium text-foreground"
                    >
                        Alasan Rejection
                    </label>

                    <textarea
                        id="review_reason"
                        rows={4}
                        value={
                            form.data
                                .review_reason
                        }
                        onChange={(event) =>
                            form.setData(
                                "review_reason",
                                event.target
                                    .value,
                            )
                        }
                        className={
                            textareaClassName
                        }
                        placeholder="Jelaskan requirement atau evidence yang harus diperbaiki."
                        required
                    />

                    <FieldError
                        message={
                            form.errors
                                .review_reason
                        }
                    />

                    <RequestErrors
                        errors={Object.fromEntries(
                            Object.entries(
                                form.errors,
                            ).filter(
                                ([key]) =>
                                    key !==
                                    "review_reason",
                            ),
                        )}
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
                        disabled={
                            form.processing
                        }
                    >
                        {form.processing ? (
                            <LoaderCircle
                                data-icon="inline-start"
                                className="animate-spin"
                            />
                        ) : (
                            <CircleX data-icon="inline-start" />
                        )}

                        Konfirmasi Reject
                    </Button>
                </CardFooter>
            </Card>
        </form>
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

            {readinessTypeLabel(value)}
        </span>
    );
}

function SatisfiedBadge({ value }) {
    return (
        <span
            className={[
                "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
                value
                    ? "bg-primary/10 text-primary"
                    : "bg-muted text-muted-foreground",
            ].join(" ")}
        >
            {value ? (
                <CheckCircle2 className="size-3.5" />
            ) : (
                <Clock3 className="size-3.5" />
            )}

            {value
                ? "Terpenuhi"
                : "Belum Terpenuhi"}
        </span>
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

            <p className="mt-1 whitespace-pre-wrap font-medium text-foreground">
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

function RequestErrors({ errors }) {
    const messages =
        Object.values(errors).filter(Boolean);

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-3">
            {messages.map(
                (message, index) => (
                    <p
                        key={`${message}-${index}`}
                        className="text-sm text-destructive"
                    >
                        {message}
                    </p>
                ),
            )}
        </div>
    );
}

function readinessTypeLabel(value) {
    return value === "LOGISTICS"
        ? "Logistics"
        : value === "DOCUMENT"
          ? "Document"
          : value;
}

function approvalLabel(value) {
    return (
        {
            DRAFT: "Draft",
            PENDING_APPROVAL:
                "Menunggu Persetujuan",
            APPROVED: "Disetujui",
            REJECTED: "Ditolak",
        }[value] ?? value
    );
}

function scopeLabel(value) {
    return value === "ORGANIZATION"
        ? "Organization-level"
        : value === "FORECAST"
          ? "Forecast-specific"
          : value;
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