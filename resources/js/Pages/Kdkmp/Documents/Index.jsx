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
    usePage,
} from "@inertiajs/react";
import {
    AlertTriangle,
    CheckCircle2,
    CircleX,
    FileCheck2,
    FileClock,
    Files,
    LoaderCircle,
    Pencil,
    Plus,
    RefreshCcw,
    ShieldCheck,
} from "lucide-react";
import { useState } from "react";

const inputClassName =
    "h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary focus:ring-3 focus:ring-primary/15";

const textareaClassName =
    "w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15";

export default function Index({
    records,
    requirements,
}) {
    const { auth } = usePage().props;

    const isOperator =
        auth?.user?.role ===
        "KDKMP_OPERATOR";

    const [showCreate, setShowCreate] =
        useState(false);

    const validCount =
        records.filter(
            (record) =>
                record.status === "VALID",
        ).length;

    const pendingCount =
        records.filter(
            (record) =>
                record.status === "PENDING",
        ).length;

    const invalidCount =
        records.filter((record) =>
            [
                "REVOKED",
                "EXPIRED",
            ].includes(record.status),
        ).length;

    return (
        <>
            <Head title="Document Records — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Document Records"
                pageDescription="Kelola metadata dokumen organisasi yang dapat digunakan kembali pada Document Readiness."
                headerActions={
                    isOperator ? (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() =>
                                setShowCreate(
                                    (value) =>
                                        !value,
                                )
                            }
                        >
                            <Plus data-icon="inline-start" />
                            Tambah Dokumen
                        </Button>
                    ) : null
                }
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Valid"
                            value={validCount}
                            icon={CheckCircle2}
                        />

                        <SummaryCard
                            label="Perlu Validasi"
                            value={pendingCount}
                            icon={FileClock}
                        />

                        <SummaryCard
                            label="Tidak Berlaku"
                            value={invalidCount}
                            icon={CircleX}
                        />
                    </div>

                    {showCreate &&
                        isOperator && (
                            <CreateDocumentCard
                                requirements={
                                    requirements
                                }
                                onCancel={() =>
                                    setShowCreate(
                                        false,
                                    )
                                }
                            />
                        )}

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Dokumen Organisasi
                            </CardTitle>

                            <CardDescription>
                                Record ini menyimpan
                                metadata dan masa berlaku.
                                Actual file upload bukan
                                kebutuhan MVP.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="p-0">
                            {records.length === 0 ? (
                                <EmptyState
                                    isOperator={
                                        isOperator
                                    }
                                    onCreate={() =>
                                        setShowCreate(
                                            true,
                                        )
                                    }
                                />
                            ) : (
                                <div className="divide-y divide-border">
                                    {records.map(
                                        (record) => (
                                            <DocumentRow
                                                key={
                                                    record.id
                                                }
                                                record={
                                                    record
                                                }
                                                isOperator={
                                                    isOperator
                                                }
                                            />
                                        ),
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent>
                            <div className="flex items-start gap-3">
                                <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-700" />

                                <div>
                                    <p className="font-medium text-foreground">
                                        Valid tidak berarti
                                        permanen
                                    </p>

                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        Perubahan metadata,
                                        revoke, expiry, atau
                                        perubahan revision
                                        dapat membuat
                                        Document Readiness
                                        yang sebelumnya
                                        disetujui kembali
                                        tidak ready.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </KdkmpLayout>
        </>
    );
}

function CreateDocumentCard({
    requirements,
    onCancel,
}) {
    const form = useForm({
        requirement_id: "",
        document_name: "",
        reference_number: "",
        valid_from: "",
        expires_at: "",
        notes: "",
    });

    const submit = (event) => {
        event.preventDefault();

        form.post("/kdkmp/documents", {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onCancel();
            },
        });
    };

    return (
        <form onSubmit={submit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        Tambah Document Record
                    </CardTitle>

                    <CardDescription>
                        Pilih requirement organisasi
                        yang direpresentasikan oleh
                        dokumen ini.
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-5">
                    <div>
                        <label
                            htmlFor="requirement_id"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Requirement
                        </label>

                        <select
                            id="requirement_id"
                            value={
                                form.data
                                    .requirement_id
                            }
                            onChange={(event) =>
                                form.setData(
                                    "requirement_id",
                                    event.target
                                        .value,
                                )
                            }
                            className={
                                inputClassName
                            }
                            required
                        >
                            <option value="">
                                Pilih requirement
                            </option>

                            {requirements.map(
                                (
                                    requirement,
                                ) => (
                                    <option
                                        key={
                                            requirement.id
                                        }
                                        value={
                                            requirement.id
                                        }
                                    >
                                        {
                                            requirement.label
                                        }
                                    </option>
                                ),
                            )}
                        </select>

                        <FieldError
                            message={
                                form.errors
                                    .requirement_id
                            }
                        />
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <TextField
                            label="Nama Dokumen"
                            value={
                                form.data
                                    .document_name
                            }
                            onChange={(value) =>
                                form.setData(
                                    "document_name",
                                    value,
                                )
                            }
                            error={
                                form.errors
                                    .document_name
                            }
                            required
                        />

                        <TextField
                            label="Nomor Referensi"
                            value={
                                form.data
                                    .reference_number
                            }
                            onChange={(value) =>
                                form.setData(
                                    "reference_number",
                                    value,
                                )
                            }
                            error={
                                form.errors
                                    .reference_number
                            }
                        />
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <DateTimeField
                            label="Berlaku Mulai"
                            value={
                                form.data
                                    .valid_from
                            }
                            onChange={(value) =>
                                form.setData(
                                    "valid_from",
                                    value,
                                )
                            }
                            error={
                                form.errors
                                    .valid_from
                            }
                        />

                        <DateTimeField
                            label="Berlaku Sampai"
                            value={
                                form.data
                                    .expires_at
                            }
                            onChange={(value) =>
                                form.setData(
                                    "expires_at",
                                    value,
                                )
                            }
                            error={
                                form.errors
                                    .expires_at
                            }
                        />
                    </div>

                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">
                            Catatan
                        </label>

                        <textarea
                            rows={4}
                            value={
                                form.data.notes
                            }
                            onChange={(event) =>
                                form.setData(
                                    "notes",
                                    event.target
                                        .value,
                                )
                            }
                            className={
                                textareaClassName
                            }
                            placeholder="Metadata atau konteks tambahan bila diperlukan."
                        />

                        <FieldError
                            message={
                                form.errors.notes
                            }
                        />
                    </div>
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

                        <Plus data-icon="inline-start" />
                        Simpan Document Record
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

function DocumentRow({
    record,
    isOperator,
}) {
    const [editing, setEditing] =
        useState(false);

    const [revoking, setRevoking] =
        useState(false);

    return (
        <div className="p-4">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-medium text-foreground">
                            {
                                record.document_name
                            }
                        </p>

                        <DocumentStatusBadge
                            status={
                                record.status
                            }
                        />
                    </div>

                    <p className="mt-1 text-xs text-muted-foreground">
                        {
                            record
                                .requirement
                                .label
                        }
                        {" • "}
                        {
                            record
                                .requirement
                                .requirement_code
                        }
                    </p>

                    <p className="mt-2 text-sm text-muted-foreground">
                        {record.reference_number
                            ? `Referensi ${record.reference_number}`
                            : "Tanpa nomor referensi"}
                        {" • Revision "}
                        {record.revision_no}
                    </p>
                </div>

                {isOperator && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={
                                record.status ===
                                "REVOKED"
                            }
                            onClick={() => {
                                setEditing(
                                    (value) =>
                                        !value,
                                );
                                setRevoking(
                                    false,
                                );
                            }}
                        >
                            <Pencil data-icon="inline-start" />
                            Edit
                        </Button>

                        {record.status ===
                            "PENDING" && (
                            <ValidateButton
                                record={
                                    record
                                }
                            />
                        )}

                        {record.status !==
                            "REVOKED" && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    setRevoking(
                                        (value) =>
                                            !value,
                                    );
                                    setEditing(
                                        false,
                                    );
                                }}
                            >
                                <CircleX data-icon="inline-start" />
                                Revoke
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <div className="mt-4 grid gap-4 md:grid-cols-3">
                <DetailItem
                    label="Berlaku Mulai"
                    value={formatDateTime(
                        record.valid_from,
                    )}
                />

                <DetailItem
                    label="Berlaku Sampai"
                    value={formatDateTime(
                        record.expires_at,
                    )}
                />

                <DetailItem
                    label="Dibuat Oleh"
                    value={
                        record.created_by
                            ?.name ?? "—"
                    }
                />
            </div>

            {record.notes && (
                <p className="mt-4 whitespace-pre-wrap text-sm leading-6 text-muted-foreground">
                    {record.notes}
                </p>
            )}

            {editing && (
                <EditDocumentForm
                    record={record}
                    onCancel={() =>
                        setEditing(false)
                    }
                />
            )}

            {revoking && (
                <RevokeForm
                    record={record}
                    onCancel={() =>
                        setRevoking(false)
                    }
                />
            )}
        </div>
    );
}

function EditDocumentForm({
    record,
    onCancel,
}) {
    const form = useForm({
        document_name:
            record.document_name ?? "",

        reference_number:
            record.reference_number ?? "",

        valid_from:
            toLocalDateTime(
                record.valid_from,
            ),

        expires_at:
            toLocalDateTime(
                record.expires_at,
            ),

        notes:
            record.notes ?? "",
    });

    const submit = (event) => {
        event.preventDefault();

        form.put(
            `/kdkmp/documents/${record.id}`,
            {
                preserveScroll: true,
                onSuccess: onCancel,
            },
        );
    };

    return (
        <form
            onSubmit={submit}
            className="mt-5 space-y-5 border-t border-border pt-5"
        >
            <div className="grid gap-5 md:grid-cols-2">
                <TextField
                    label="Nama Dokumen"
                    value={
                        form.data
                            .document_name
                    }
                    onChange={(value) =>
                        form.setData(
                            "document_name",
                            value,
                        )
                    }
                    error={
                        form.errors
                            .document_name
                    }
                    required
                />

                <TextField
                    label="Nomor Referensi"
                    value={
                        form.data
                            .reference_number
                    }
                    onChange={(value) =>
                        form.setData(
                            "reference_number",
                            value,
                        )
                    }
                    error={
                        form.errors
                            .reference_number
                    }
                />
            </div>

            <div className="grid gap-5 md:grid-cols-2">
                <DateTimeField
                    label="Berlaku Mulai"
                    value={
                        form.data.valid_from
                    }
                    onChange={(value) =>
                        form.setData(
                            "valid_from",
                            value,
                        )
                    }
                    error={
                        form.errors.valid_from
                    }
                />

                <DateTimeField
                    label="Berlaku Sampai"
                    value={
                        form.data.expires_at
                    }
                    onChange={(value) =>
                        form.setData(
                            "expires_at",
                            value,
                        )
                    }
                    error={
                        form.errors.expires_at
                    }
                />
            </div>

            <div>
                <label className="mb-2 block text-sm font-medium text-foreground">
                    Catatan
                </label>

                <textarea
                    rows={3}
                    value={
                        form.data.notes
                    }
                    onChange={(event) =>
                        form.setData(
                            "notes",
                            event.target.value,
                        )
                    }
                    className={
                        textareaClassName
                    }
                />

                <FieldError
                    message={
                        form.errors.notes
                    }
                />
            </div>

            <RequestErrors
                errors={form.errors}
            />

            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                >
                    Batal
                </Button>

                <Button
                    type="submit"
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

                    Simpan Perubahan
                </Button>
            </div>
        </form>
    );
}

function ValidateButton({ record }) {
    const form = useForm({});

    return (
        <Button
            type="button"
            size="sm"
            disabled={form.processing}
            onClick={() =>
                form.post(
                    `/kdkmp/documents/${record.id}/validate`,
                    {
                        preserveScroll: true,
                    },
                )
            }
        >
            {form.processing ? (
                <LoaderCircle
                    data-icon="inline-start"
                    className="animate-spin"
                />
            ) : (
                <ShieldCheck data-icon="inline-start" />
            )}

            Tandai Valid
        </Button>
    );
}

function RevokeForm({
    record,
    onCancel,
}) {
    const form = useForm({
        reason: "",
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            `/kdkmp/documents/${record.id}/revoke`,
            {
                preserveScroll: true,
                onSuccess: onCancel,
            },
        );
    };

    return (
        <form
            onSubmit={submit}
            className="mt-5 border-t border-border pt-5"
        >
            <div className="rounded-xl border border-destructive/20 bg-destructive/5 p-4">
                <label className="block text-sm font-medium text-foreground">
                    Alasan Revoke
                </label>

                <textarea
                    rows={3}
                    value={
                        form.data.reason
                    }
                    onChange={(event) =>
                        form.setData(
                            "reason",
                            event.target.value,
                        )
                    }
                    className={`${textareaClassName} mt-2`}
                    placeholder="Jelaskan mengapa dokumen tidak lagi berlaku."
                    required
                />

                <FieldError
                    message={
                        form.errors.reason
                    }
                />

                <div className="mt-4 flex justify-end gap-2">
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
                        {form.processing && (
                            <LoaderCircle
                                data-icon="inline-start"
                                className="animate-spin"
                            />
                        )}

                        <CircleX data-icon="inline-start" />
                        Revoke Dokumen
                    </Button>
                </div>
            </div>
        </form>
    );
}

function DocumentStatusBadge({ status }) {
    const config = {
        VALID: {
            label: "Valid",
            icon: CheckCircle2,
            className:
                "bg-primary/10 text-primary",
        },

        PENDING: {
            label: "Perlu Validasi",
            icon: FileClock,
            className:
                "bg-muted text-muted-foreground",
        },

        REVOKED: {
            label: "Revoked",
            icon: CircleX,
            className:
                "bg-destructive/10 text-destructive",
        },

        EXPIRED: {
            label: "Expired",
            icon: RefreshCcw,
            className:
                "bg-amber-500/10 text-amber-800",
        },
    };

    const selected =
        config[status] ?? {
            label: status,
            icon: Files,
            className:
                "bg-muted text-muted-foreground",
        };

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

function EmptyState({
    isOperator,
    onCreate,
}) {
    return (
        <div className="flex min-h-72 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <FileCheck2 className="size-5" />
            </div>

            <h2 className="mt-4 font-semibold text-foreground">
                Belum ada Document Record
            </h2>

            <p className="mt-1 max-w-lg text-sm leading-6 text-muted-foreground">
                Document Record organisasi dapat
                digunakan kembali untuk Document
                Readiness selama masih valid terhadap
                requirement dan periode Forecast.
            </p>

            {isOperator && (
                <Button
                    type="button"
                    className="mt-5"
                    onClick={onCreate}
                >
                    <Plus data-icon="inline-start" />
                    Tambah Dokumen
                </Button>
            )}
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

function TextField({
    label,
    value,
    onChange,
    error,
    required = false,
}) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-foreground">
                {label}
            </label>

            <input
                type="text"
                value={value}
                onChange={(event) =>
                    onChange(
                        event.target.value,
                    )
                }
                className={inputClassName}
                required={required}
            />

            <FieldError message={error} />
        </div>
    );
}

function DateTimeField({
    label,
    value,
    onChange,
    error,
}) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-foreground">
                {label}
            </label>

            <input
                type="datetime-local"
                value={value}
                onChange={(event) =>
                    onChange(
                        event.target.value,
                    )
                }
                className={inputClassName}
            />

            <FieldError message={error} />
        </div>
    );
}

function DetailItem({
    label,
    value,
}) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>

            <p className="mt-1 font-medium text-foreground">
                {value}
            </p>
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

function toLocalDateTime(value) {
    if (!value) {
        return "";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return "";
    }

    const pad = (number) =>
        String(number).padStart(2, "0");

    return [
        date.getFullYear(),
        "-",
        pad(date.getMonth() + 1),
        "-",
        pad(date.getDate()),
        "T",
        pad(date.getHours()),
        ":",
        pad(date.getMinutes()),
    ].join("");
}

function formatDateTime(value) {
    if (!value) {
        return "Tidak ditentukan";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}