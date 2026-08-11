import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import SppgLayout from "@/Layouts/SppgLayout";
import { Head, router, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    Clock3,
    LoaderCircle,
    Pencil,
    Send,
    XCircle,
    FileCheck2,
} from "lucide-react";
import { useState } from "react";


const inputClassName =
    "h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary focus:ring-3 focus:ring-primary/15";

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

export default function Show({ forecast }) {
    const [activeAction, setActiveAction] =
        useState(null);

    const publishForm = useForm({
        version: forecast.version,
    });

    const reviseForm = useForm({
        target_volume: forecast.target_volume,
        required_start_at: toLocalDateTime(
            forecast.required_start_at,
        ),
        required_end_at: toLocalDateTime(
            forecast.required_end_at,
        ),
        reason: "",
        version: forecast.version,
    });

    const cancelForm = useForm({
        cancellation_reason: "",
        version: forecast.version,
    });

    const closeForm = useForm({
        version: forecast.version,
    });

    const publish = () => {
        publishForm.post(
            `/sppg/forecasts/${forecast.id}/publish`,
            {
                preserveScroll: true,
            },
        );
    };

    const revise = (event) => {
        event.preventDefault();

        reviseForm.post(
            `/sppg/forecasts/${forecast.id}/revise`,
            {
                preserveScroll: true,
            },
        );
    };

    const cancel = (event) => {
        event.preventDefault();

        cancelForm.post(
            `/sppg/forecasts/${forecast.id}/cancel`,
            {
                preserveScroll: true,
            },
        );
    };

    const close = () => {
        closeForm.post(
            `/sppg/forecasts/${forecast.id}/close`,
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head
                title={`${forecast.forecast_code} — SiagaPasok`}
            />

            <SppgLayout
                pageTitle={forecast.forecast_code}
                pageDescription={`${forecast.commodity.name} • Version ${forecast.version}`}
                headerActions={
    <>
        {forecast.status ===
            "PUBLISHED" && (
            <Button
                type="button"
                size="sm"
                onClick={() =>
                    router.visit(
                        `/sppg/forecasts/${forecast.id}/readiness`,
                    )
                }
            >
                <FileCheck2 data-icon="inline-start" />
                Lihat Readiness
            </Button>
        )}

        <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() =>
                router.visit(
                    "/sppg/forecasts",
                )
            }
        >
            <ArrowLeft data-icon="inline-start" />
            Daftar Forecast
        </Button>
    </>
}
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Status"
                            value={
                                forecast.status_label
                            }
                        />

                        <SummaryCard
                            label="Target Volume"
                            value={formatVolume(
                                forecast,
                            )}
                        />

                        <SummaryCard
                            label="Freshness Interval"
                            value={
                                forecast.freshness_interval_hours
                                    ? `${forecast.freshness_interval_hours} jam`
                                    : "Tidak ditentukan"
                            }
                        />
                    </div>

                    <Card>
                        <CardHeader className="border-b">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>
                                        Detail Kebutuhan
                                    </CardTitle>

                                    <CardDescription>
                                        Informasi Forecast yang
                                        menjadi acuan direct supply
                                        planning setelah
                                        dipublikasikan.
                                    </CardDescription>
                                </div>

                                <ForecastStatusBadge
                                    forecast={forecast}
                                />
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                <DetailItem
                                    label="Komoditas"
                                    value={
                                        forecast.commodity
                                            .name
                                    }
                                    description={
                                        forecast.commodity
                                            .code
                                    }
                                />

                                <DetailItem
                                    label="Unit"
                                    value={
                                        forecast.unit.name
                                    }
                                    description={
                                        forecast.unit.symbol
                                    }
                                />
                            </div>

                            <div className="grid gap-6 md:grid-cols-2">
                                <DetailItem
                                    icon={CalendarDays}
                                    label="Mulai Dibutuhkan"
                                    value={formatDateTime(
                                        forecast.required_start_at,
                                    )}
                                />

                                <DetailItem
                                    icon={CalendarDays}
                                    label="Batas Akhir"
                                    value={formatDateTime(
                                        forecast.required_end_at,
                                    )}
                                />
                            </div>

                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Catatan
                                </p>

                                <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                                    {forecast.notes ||
                                        "Tidak ada catatan."}
                                </p>
                            </div>

                            {forecast.published_at && (
                                <DetailItem
                                    icon={Send}
                                    label="Dipublikasikan"
                                    value={formatDateTime(
                                        forecast.published_at,
                                    )}
                                />
                            )}

                            {forecast.closed_at && (
                                <DetailItem
                                    icon={CheckCircle2}
                                    label="Ditutup"
                                    value={formatDateTime(
                                        forecast.closed_at,
                                    )}
                                />
                            )}

                            {forecast.cancelled_at && (
                                <div className="rounded-xl border border-border bg-muted/35 p-4">
                                    <DetailItem
                                        icon={XCircle}
                                        label="Dibatalkan"
                                        value={formatDateTime(
                                            forecast.cancelled_at,
                                        )}
                                    />

                                    <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                        {
                                            forecast.cancellation_reason
                                        }
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {(forecast.can?.edit_draft ||
                        forecast.can?.publish ||
                        forecast.can?.revise ||
                        forecast.can?.cancel ||
                        forecast.can?.close) && (
                        <Card>
                            <CardHeader className="border-b">
                                <CardTitle>
                                    Aksi Forecast
                                </CardTitle>

                                <CardDescription>
                                    Perubahan lifecycle dilakukan
                                    melalui command eksplisit dan
                                    diverifikasi kembali oleh
                                    backend.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {forecast.can
                                        ?.edit_draft && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                router.visit(
                                                    `/sppg/forecasts/${forecast.id}/edit`,
                                                )
                                            }
                                        >
                                            <Pencil data-icon="inline-start" />
                                            Edit Draft
                                        </Button>
                                    )}

                                    {forecast.can
                                        ?.publish && (
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "publish"
                                                        ? null
                                                        : "publish",
                                                )
                                            }
                                        >
                                            <Send data-icon="inline-start" />
                                            Publish
                                        </Button>
                                    )}

                                    {forecast.can
                                        ?.revise && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "revise"
                                                        ? null
                                                        : "revise",
                                                )
                                            }
                                        >
                                            <Pencil data-icon="inline-start" />
                                            Revisi
                                        </Button>
                                    )}

                                    {forecast.can
                                        ?.close && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "close"
                                                        ? null
                                                        : "close",
                                                )
                                            }
                                        >
                                            <CheckCircle2 data-icon="inline-start" />
                                            Tutup
                                        </Button>
                                    )}

                                    {forecast.can
                                        ?.cancel && (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "cancel"
                                                        ? null
                                                        : "cancel",
                                                )
                                            }
                                        >
                                            <XCircle data-icon="inline-start" />
                                            Batalkan
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {activeAction === "publish" && (
                        <PublishPanel
                            form={publishForm}
                            onConfirm={publish}
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}

                    {activeAction === "revise" && (
                        <RevisionPanel
                            form={reviseForm}
                            forecast={forecast}
                            onSubmit={revise}
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}

                    {activeAction === "close" && (
                        <ClosePanel
                            form={closeForm}
                            onConfirm={close}
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}

                    {activeAction === "cancel" && (
                        <CancelPanel
                            form={cancelForm}
                            onSubmit={cancel}
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}

                    <Card>
                        <CardContent>
                            <div className="flex items-start gap-3">
                                <Clock3 className="mt-0.5 size-5 shrink-0 text-primary" />

                                <div>
                                    <p className="font-medium text-foreground">
                                        Version{" "}
                                        {forecast.version}
                                    </p>

                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        Setiap perubahan
                                        Forecast menaikkan version.
                                        Jika data berubah sejak
                                        halaman dibuka, backend
                                        akan menolak perubahan
                                        stale dan meminta data
                                        dimuat ulang.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </SppgLayout>
        </>
    );
}

function PublishPanel({
    form,
    onConfirm,
    onCancel,
}) {
    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Publish Forecast?
                </CardTitle>

                <CardDescription>
                    Setelah dipublikasikan, Forecast
                    tersedia kepada PRIMARY KDKMP yang
                    relevan dan Draft tidak lagi dapat
                    diedit langsung.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <FieldError
                    message={form.errors.network}
                />

                <FieldError
                    message={form.errors.version}
                />

                <FieldError
                    message={form.errors.status}
                />
            </CardContent>

            <CardFooter className="justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                >
                    Kembali
                </Button>

                <Button
                    type="button"
                    disabled={form.processing}
                    onClick={onConfirm}
                >
                    {form.processing && (
                        <LoaderCircle
                            data-icon="inline-start"
                            className="animate-spin"
                        />
                    )}

                    <Send data-icon="inline-start" />
                    Publish Forecast
                </Button>
            </CardFooter>
        </Card>
    );
}

function RevisionPanel({
    form,
    forecast,
    onSubmit,
    onCancel,
}) {
    return (
        <form onSubmit={onSubmit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        Revisi Forecast
                    </CardTitle>

                    <CardDescription>
                        Published Forecast hanya dapat
                        direvisi pada target volume dan
                        periode kebutuhan. Alasan wajib
                        dicatat untuk audit.
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-5">
                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">
                            Target Volume
                        </label>

                        <div className="relative max-w-md">
                            <input
                                type="number"
                                step="any"
                                min="0"
                                value={
                                    form.data
                                        .target_volume
                                }
                                onChange={(event) =>
                                    form.setData(
                                        "target_volume",
                                        event.target
                                            .value,
                                    )
                                }
                                className={`${inputClassName} pr-20`}
                                required
                            />

                            <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                {forecast.unit.symbol}
                            </div>
                        </div>

                        <FieldError
                            message={
                                form.errors
                                    .target_volume
                            }
                        />
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">
                                Mulai Dibutuhkan
                            </label>

                            <input
                                type="datetime-local"
                                value={
                                    form.data
                                        .required_start_at
                                }
                                onChange={(event) =>
                                    form.setData(
                                        "required_start_at",
                                        event.target
                                            .value,
                                    )
                                }
                                className={inputClassName}
                                required
                            />

                            <FieldError
                                message={
                                    form.errors
                                        .required_start_at
                                }
                            />
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">
                                Batas Akhir
                            </label>

                            <input
                                type="datetime-local"
                                value={
                                    form.data
                                        .required_end_at
                                }
                                onChange={(event) =>
                                    form.setData(
                                        "required_end_at",
                                        event.target
                                            .value,
                                    )
                                }
                                className={inputClassName}
                                required
                            />

                            <FieldError
                                message={
                                    form.errors
                                        .required_end_at
                                }
                            />
                        </div>
                    </div>

                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">
                            Alasan Revisi
                        </label>

                        <textarea
                            rows={4}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData(
                                    "reason",
                                    event.target.value,
                                )
                            }
                            className="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary focus:ring-3 focus:ring-primary/15"
                            placeholder="Jelaskan alasan perubahan kebutuhan."
                            required
                        />

                        <FieldError
                            message={
                                form.errors.reason
                            }
                        />

                        <FieldError
                            message={
                                form.errors.revision
                            }
                        />

                        <FieldError
                            message={
                                form.errors.version
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
                        disabled={form.processing}
                    >
                        {form.processing && (
                            <LoaderCircle
                                data-icon="inline-start"
                                className="animate-spin"
                            />
                        )}

                        Simpan Revisi
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

function ClosePanel({
    form,
    onConfirm,
    onCancel,
}) {
    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Tutup Forecast?
                </CardTitle>

                <CardDescription>
                    CLOSED adalah terminal state.
                    Forecast tidak dapat direvisi atau
                    dibatalkan lagi setelah ditutup.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <FieldError
                    message={form.errors.version}
                />

                <FieldError
                    message={form.errors.status}
                />
            </CardContent>

            <CardFooter className="justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                >
                    Kembali
                </Button>

                <Button
                    type="button"
                    disabled={form.processing}
                    onClick={onConfirm}
                >
                    {form.processing && (
                        <LoaderCircle
                            data-icon="inline-start"
                            className="animate-spin"
                        />
                    )}

                    <CheckCircle2 data-icon="inline-start" />
                    Tutup Forecast
                </Button>
            </CardFooter>
        </Card>
    );
}

function CancelPanel({
    form,
    onSubmit,
    onCancel,
}) {
    return (
        <form onSubmit={onSubmit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        Batalkan Forecast
                    </CardTitle>

                    <CardDescription>
                        Forecast CANCELLED menjadi
                        read-only. Alasan pembatalan
                        wajib dicatat.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <label className="mb-2 block text-sm font-medium text-foreground">
                        Alasan Pembatalan
                    </label>

                    <textarea
                        rows={4}
                        value={
                            form.data
                                .cancellation_reason
                        }
                        onChange={(event) =>
                            form.setData(
                                "cancellation_reason",
                                event.target.value,
                            )
                        }
                        className="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary focus:ring-3 focus:ring-primary/15"
                        placeholder="Jelaskan alasan pembatalan Forecast."
                        required
                    />

                    <FieldError
                        message={
                            form.errors
                                .cancellation_reason
                        }
                    />

                    <FieldError
                        message={form.errors.version}
                    />

                    <FieldError
                        message={form.errors.status}
                    />
                </CardContent>

                <CardFooter className="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                    >
                        Kembali
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

                        <XCircle data-icon="inline-start" />
                        Batalkan Forecast
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
    icon: Icon,
    label,
    value,
    description,
}) {
    return (
        <div className="flex items-start gap-3">
            {Icon && (
                <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-4" />
                </div>
            )}

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
        </div>
    );
}

function ForecastStatusBadge({ forecast }) {
    const classes = {
        DRAFT:
            "bg-muted text-muted-foreground",
        PUBLISHED:
            "bg-primary/10 text-primary",
        CLOSED:
            "bg-muted text-foreground",
        CANCELLED:
            "bg-muted text-muted-foreground",
    };

    return (
        <span
            className={[
                "inline-flex rounded-full px-2.5 py-1 text-xs font-medium",
                classes[forecast.status] ??
                    "bg-muted text-muted-foreground",
            ].join(" ")}
        >
            {forecast.status_label}
        </span>
    );
}

function formatVolume(forecast) {
    const number = Number(forecast.target_volume);

    if (!Number.isFinite(number)) {
        return `${forecast.target_volume} ${forecast.unit.symbol}`;
    }

    return `${new Intl.NumberFormat("id-ID", {
        maximumFractionDigits:
            forecast.unit.decimal_precision ?? 2,
    }).format(number)} ${forecast.unit.symbol}`;
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