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
    FileClock,
    FilePenLine,
    LoaderCircle,
    RefreshCcw,
    Send,
    Truck,
} from "lucide-react";

const inputClassName =
    "h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary focus:ring-3 focus:ring-primary/15";

const textareaClassName =
    "w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15";

export default function Show({
    checklist,
    readiness,
    availableDocuments = [],
    can,
}) {
    const submitForm = useForm({});
    const revisionForm = useForm({});

    const currentReady =
        checklist.readiness_type === "LOGISTICS"
            ? readiness.logistics_ready
            : readiness.document_ready;

    const reasonCodes =
        checklist.readiness_type === "LOGISTICS"
            ? readiness.logistics_reason_codes
            : readiness.document_reason_codes;

    const submit = () => {
        submitForm.post(
            `/kdkmp/readiness/${checklist.id}/submit`,
            {
                preserveScroll: true,
            },
        );
    };

    const createRevision = () => {
        revisionForm.post(
            `/kdkmp/readiness/${checklist.id}/revisions`,
        );
    };

    return (
        <>
            <Head
                title={`${readinessTypeLabel(
                    checklist.readiness_type,
                )} Readiness — SiagaPasok`}
            />

            <KdkmpLayout
                pageTitle={`${readinessTypeLabel(
                    checklist.readiness_type,
                )} Readiness`}
                pageDescription={`${checklist.forecast.forecast_code} • Version ${checklist.version_no}`}
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                "/kdkmp/readiness",
                            )
                        }
                    >
                        <ArrowLeft data-icon="inline-start" />
                        Daftar Readiness
                    </Button>
                }
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Jenis Readiness"
                            value={readinessTypeLabel(
                                checklist.readiness_type,
                            )}
                        />

                        <SummaryCard
                            label="Checklist Version"
                            value={`Version ${checklist.version_no}`}
                        />

                        <SummaryCard
                            label="Approval"
                            value={approvalLabel(
                                checklist.status,
                            )}
                        />
                    </div>

                    <CurrentTruthPanel
                        ready={currentReady}
                        type={
                            checklist.readiness_type
                        }
                        reasonCodes={reasonCodes}
                    />

                    {checklist.review_reason && (
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
                                                checklist.review_reason
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
                                Konteks Forecast
                            </CardTitle>

                            <CardDescription>
                                Readiness ini hanya berlaku
                                untuk contributor, Forecast,
                                dan business version yang
                                sedang dievaluasi.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                <DetailItem
                                    label="Forecast"
                                    value={
                                        checklist
                                            .forecast
                                            .forecast_code
                                    }
                                    description={
                                        checklist
                                            .forecast
                                            .sppg_name
                                    }
                                />

                                <DetailItem
                                    label="Komoditas"
                                    value={
                                        checklist
                                            .forecast
                                            .commodity_name
                                    }
                                />

                                <DetailItem
                                    label="Target"
                                    value={`${formatNumber(
                                        checklist
                                            .forecast
                                            .target_volume,
                                    )} ${
                                        checklist
                                            .forecast
                                            .unit_symbol
                                    }`}
                                />

                                <DetailItem
                                    label="Forecast Version"
                                    value={`Current v${checklist.forecast.version}`}
                                    description={`Checklist snapshot v${checklist.forecast_version}`}
                                />

                                <DetailItem
                                    label="Mulai Dibutuhkan"
                                    value={formatDateTime(
                                        checklist
                                            .forecast
                                            .required_start_at,
                                    )}
                                />

                                <DetailItem
                                    label="Batas Akhir"
                                    value={formatDateTime(
                                        checklist
                                            .forecast
                                            .required_end_at,
                                    )}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Requirement Checklist
                            </CardTitle>

                            <CardDescription>
                                Required item harus terpenuhi
                                sebelum checklist dapat
                                diajukan. Payload yang sudah
                                disubmit tidak dapat diedit
                                in-place.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            {checklist.items.map(
                                (item) => (
                                    <ReadinessItemCard
                                        key={item.id}
                                        checklist={
                                            checklist
                                        }
                                        item={item}
                                        editable={
                                            can.update_item
                                        }
                                        documents={availableDocuments.filter(
                                            (
                                                document,
                                            ) =>
                                                document.requirement_id ===
                                                item.requirement_id,
                                        )}
                                    />
                                ),
                            )}
                        </CardContent>
                    </Card>

                    {(can.submit ||
                        can.create_revision) && (
                        <Card>
                            <CardHeader className="border-b">
                                <CardTitle>
                                    Aksi Operator
                                </CardTitle>

                                <CardDescription>
                                    Submit mengunci current
                                    payload untuk review
                                    Manager. Perubahan setelah
                                    terminal review menggunakan
                                    revision baru.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {can.submit && (
                                        <Button
                                            type="button"
                                            disabled={
                                                submitForm.processing
                                            }
                                            onClick={
                                                submit
                                            }
                                        >
                                            {submitForm.processing ? (
                                                <LoaderCircle
                                                    data-icon="inline-start"
                                                    className="animate-spin"
                                                />
                                            ) : (
                                                <Send data-icon="inline-start" />
                                            )}

                                            Ajukan ke Manager
                                        </Button>
                                    )}

                                    {can.create_revision && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={
                                                revisionForm.processing
                                            }
                                            onClick={
                                                createRevision
                                            }
                                        >
                                            {revisionForm.processing ? (
                                                <LoaderCircle
                                                    data-icon="inline-start"
                                                    className="animate-spin"
                                                />
                                            ) : (
                                                <RefreshCcw data-icon="inline-start" />
                                            )}

                                            Buat Revision Baru
                                        </Button>
                                    )}
                                </div>

                                <RequestErrors
                                    errors={{
                                        ...submitForm.errors,
                                        ...revisionForm.errors,
                                    }}
                                />
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Approval & Audit Context
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <div className="grid gap-5 md:grid-cols-3">
                                <DetailItem
                                    label="Maker"
                                    value={
                                        checklist
                                            .prepared_by
                                            ?.name ?? "—"
                                    }
                                />

                                <DetailItem
                                    label="Submitter"
                                    value={
                                        checklist
                                            .submitted_by
                                            ?.name ?? "—"
                                    }
                                    description={formatDateTime(
                                        checklist.submitted_at,
                                    )}
                                />

                                <DetailItem
                                    label="Reviewer"
                                    value={
                                        checklist
                                            .reviewed_by
                                            ?.name ?? "—"
                                    }
                                    description={formatDateTime(
                                        checklist.reviewed_at,
                                    )}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </KdkmpLayout>
        </>
    );
}

function ReadinessItemCard({
    checklist,
    item,
    editable,
    documents,
}) {
    const form = useForm({
        is_satisfied:
            Boolean(item.is_satisfied),

        note:
            item.note ?? "",

        document_record_id:
            item.document_record_id ?? "",
    });

    const save = (event) => {
        event.preventDefault();

        form.transform((data) => ({
            is_satisfied:
                Boolean(data.is_satisfied),

            note:
                data.note || null,

            document_record_id:
                data.document_record_id
                    ? Number(
                          data.document_record_id,
                      )
                    : null,
        }));

        form.put(
            `/kdkmp/readiness/${checklist.id}/items/${item.id}`,
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <form onSubmit={save}>
            <div className="rounded-xl border border-border p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="font-medium text-foreground">
                                {
                                    item
                                        .requirement
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
                                item
                                    .requirement
                                    .code
                            }
                            {" • "}
                            {scopeLabel(
                                item
                                    .requirement
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

                {editable ? (
                    <div className="mt-5 space-y-5 border-t border-border pt-5">
                        <label className="flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                checked={
                                    form.data
                                        .is_satisfied
                                }
                                onChange={(
                                    event,
                                ) =>
                                    form.setData(
                                        "is_satisfied",
                                        event.target
                                            .checked,
                                    )
                                }
                                className="mt-1 size-4 rounded border-border"
                            />

                            <span>
                                <span className="block text-sm font-medium text-foreground">
                                    Requirement
                                    terpenuhi
                                </span>

                                <span className="mt-0.5 block text-xs leading-5 text-muted-foreground">
                                    Tandai hanya
                                    berdasarkan kondisi
                                    operasional atau
                                    evidence yang benar.
                                </span>
                            </span>
                        </label>

                        {checklist.readiness_type ===
                            "DOCUMENT" && (
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Document Record
                                </label>

                                <select
                                    value={
                                        form.data
                                            .document_record_id
                                    }
                                    onChange={(
                                        event,
                                    ) =>
                                        form.setData(
                                            "document_record_id",
                                            event
                                                .target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClassName
                                    }
                                >
                                    <option value="">
                                        Tidak ada
                                        document
                                        terhubung
                                    </option>

                                    {documents.map(
                                        (
                                            document,
                                        ) => (
                                            <option
                                                key={
                                                    document.id
                                                }
                                                value={
                                                    document.id
                                                }
                                            >
                                                {
                                                    document.document_name
                                                }
                                                {" — "}
                                                {
                                                    document.status
                                                }
                                                {" — rev "}
                                                {
                                                    document.revision_no
                                                }
                                            </option>
                                        ),
                                    )}
                                </select>

                                {documents.length ===
                                    0 && (
                                    <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                        Belum ada
                                        Document Record
                                        untuk requirement
                                        ini. Kelola dokumen
                                        dari menu Document
                                        Records.
                                    </p>
                                )}

                                <FieldError
                                    message={
                                        form.errors
                                            .document_record_id
                                    }
                                />
                            </div>
                        )}

                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">
                                Catatan
                            </label>

                            <textarea
                                rows={3}
                                value={
                                    form.data.note
                                }
                                onChange={(
                                    event,
                                ) =>
                                    form.setData(
                                        "note",
                                        event.target
                                            .value,
                                    )
                                }
                                className={
                                    textareaClassName
                                }
                                placeholder="Tambahkan catatan operasional bila diperlukan."
                            />

                            <FieldError
                                message={
                                    form.errors.note
                                }
                            />
                        </div>

                        <FieldError
                            message={
                                form.errors
                                    .is_satisfied
                            }
                        />

                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                size="sm"
                                disabled={
                                    form.processing
                                }
                            >
                                {form.processing && (
                                    <LoaderCircle
                                        data-icon="inline-start"
                                        className="animate-spin"
                                    />
                                )}

                                Simpan Requirement
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="mt-4 space-y-4 border-t border-border pt-4">
                        {item.note && (
                            <DetailItem
                                label="Catatan"
                                value={
                                    item.note
                                }
                            />
                        )}

                        {item.document_record && (
                            <DocumentSnapshot
                                document={
                                    item.document_record
                                }
                                frozenRevision={
                                    item.document_record_revision_no
                                }
                            />
                        )}
                    </div>
                )}
            </div>
        </form>
    );
}

function CurrentTruthPanel({
    ready,
    type,
    reasonCodes,
}) {
    if (ready) {
        return (
            <Card className="border-primary/25">
                <CardContent>
                    <div className="flex items-start gap-3">
                        <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-primary" />

                        <div>
                            <p className="font-medium text-foreground">
                                {readinessTypeLabel(
                                    type,
                                )}{" "}
                                Ready
                            </p>

                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                Current approved
                                checklist masih memenuhi
                                seluruh dependency yang
                                dievaluasi backend.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="border-amber-500/30">
            <CardContent>
                <div className="flex items-start gap-3">
                    <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-700" />

                    <div>
                        <p className="font-medium text-foreground">
                            {readinessTypeLabel(type)}{" "}
                            Belum Ready
                        </p>

                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                            Status ini merupakan hasil
                            evaluasi current backend
                            truth, bukan toggle manual.
                        </p>

                        {reasonCodes.length > 0 && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {reasonCodes.map(
                                    (code) => (
                                        <span
                                            key={
                                                code
                                            }
                                            className="rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-800"
                                        >
                                            {reasonLabel(
                                                code,
                                            )}
                                        </span>
                                    ),
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function DocumentSnapshot({
    document,
    frozenRevision,
}) {
    return (
        <div className="rounded-lg bg-muted/35 p-4">
            <div className="flex items-start gap-3">
                <FileCheck2 className="mt-0.5 size-4 shrink-0 text-muted-foreground" />

                <div>
                    <p className="font-medium text-foreground">
                        {
                            document.document_name
                        }
                    </p>

                    <p className="mt-1 text-xs text-muted-foreground">
                        {document.reference_number ||
                            "Tanpa nomor referensi"}
                        {" • "}
                        {document.status}
                        {" • Current rev "}
                        {document.revision_no}
                    </p>

                    {frozenRevision && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            Approval snapshot:
                            revision{" "}
                            {frozenRevision}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

function RequestErrors({ errors }) {
    const messages =
        Object.values(errors).filter(Boolean);

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="mt-4 rounded-lg border border-destructive/25 bg-destructive/5 p-3">
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

            {value ? "Terpenuhi" : "Belum Terpenuhi"}
        </span>
    );
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

function readinessTypeLabel(value) {
    return value === "LOGISTICS"
        ? "Logistics"
        : value === "DOCUMENT"
          ? "Document"
          : value;
}

function scopeLabel(value) {
    return value === "ORGANIZATION"
        ? "Organization-level"
        : value === "FORECAST"
          ? "Forecast-specific"
          : value;
}

function reasonLabel(code) {
    const labels = {
        NOT_CURRENT_CONTRIBUTOR:
            "Bukan contributor aktif",
        CHECKLIST_MISSING:
            "Checklist belum tersedia",
        CHECKLIST_NOT_APPROVED:
            "Belum disetujui Manager",
        FORECAST_VERSION_STALE:
            "Forecast telah direvisi",
        REQUIRED_ITEM_UNSATISFIED:
            "Requirement wajib belum terpenuhi",
        DOCUMENT_INVALID:
            "Dokumen tidak lagi valid",
        FORECAST_WINDOW_ENDED:
            "Periode Forecast telah berakhir",
    };

    return labels[code] ?? code;
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