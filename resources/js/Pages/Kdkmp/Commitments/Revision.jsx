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
    LoaderCircle,
} from "lucide-react";

const inputClassName =
    "h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary focus:ring-3 focus:ring-primary/15";

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

export default function Revision({
    commitment,
    baseVersion,
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        min_volume:
            baseVersion.min_volume,

        max_volume:
            baseVersion.max_volume,

        unit_id:
            baseVersion.unit_id,

        availability_start_at:
            toLocalDateTime(
                baseVersion.availability_start_at,
            ),

        availability_end_at:
            toLocalDateTime(
                baseVersion.availability_end_at,
            ),

        notes:
            baseVersion.notes ?? "",

        change_reason: "",

        operator_justification:
            baseVersion.operator_justification ??
            "",
    });

    const expectedMaximum =
        commitment.expected_harvest
            ? Number(
                  commitment
                      .expected_harvest
                      .expected_max_volume,
              )
            : null;

    const proposedMaximum =
        Number(data.max_volume);

    const exceedsExpectedHarvest =
        expectedMaximum !== null &&
        Number.isFinite(
            expectedMaximum,
        ) &&
        Number.isFinite(
            proposedMaximum,
        ) &&
        proposedMaximum >
            expectedMaximum;

    const submit = (event) => {
        event.preventDefault();

        post(
            `/kdkmp/commitments/${commitment.id}/revisions`,
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head
                title={`Revisi Commitment #${commitment.id} — SiagaPasok`}
            />

            <KdkmpLayout
                pageTitle="Revisi Komitmen Pasokan"
                pageDescription={`${commitment.forecast.forecast_code} • ${commitment.forecast.commodity_name}`}
            >
                <div className="max-w-5xl">
                    <form onSubmit={submit}>
                        <Card>
                            <CardHeader className="border-b">
                                <CardTitle>
                                    Buat Version Baru
                                </CardTitle>

                                <CardDescription>
                                    Approved payload
                                    tidak diubah.
                                    Revision membuat
                                    Commitment Version
                                    baru untuk diajukan
                                    kembali kepada
                                    Manager.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-7">
                                <div className="grid gap-4 rounded-xl border border-border bg-muted/25 p-4 md:grid-cols-2">
                                    <ContextItem
                                        label="Forecast"
                                        value={
                                            commitment
                                                .forecast
                                                .forecast_code
                                        }
                                    />

                                    <ContextItem
                                        label="Produsen"
                                        value={
                                            commitment
                                                .producer
                                                .name
                                        }
                                        description={
                                            commitment
                                                .producer
                                                .producer_code
                                        }
                                    />

                                    <ContextItem
                                        label="Base Version"
                                        value={`Version ${baseVersion.version_no}`}
                                    />

                                    <ContextItem
                                        label="Confidence Saat Ini"
                                        value={
                                            commitment.current_confidence ??
                                            "Belum ada"
                                        }
                                    />
                                </div>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <VolumeField
                                        id="min_volume"
                                        label="Minimum Volume Baru"
                                        value={
                                            data.min_volume
                                        }
                                        symbol={
                                            commitment
                                                .forecast
                                                .unit
                                                .symbol
                                        }
                                        onChange={(
                                            value,
                                        ) =>
                                            setData(
                                                "min_volume",
                                                value,
                                            )
                                        }
                                        error={
                                            errors.min_volume
                                        }
                                    />

                                    <VolumeField
                                        id="max_volume"
                                        label="Maximum Volume Baru"
                                        value={
                                            data.max_volume
                                        }
                                        symbol={
                                            commitment
                                                .forecast
                                                .unit
                                                .symbol
                                        }
                                        onChange={(
                                            value,
                                        ) =>
                                            setData(
                                                "max_volume",
                                                value,
                                            )
                                        }
                                        error={
                                            errors.max_volume
                                        }
                                    />
                                </div>

                                <FieldError
                                    message={
                                        errors.unit_id
                                    }
                                />

                                {exceedsExpectedHarvest && (
                                    <div className="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                                        <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-400" />

                                        <div>
                                            <p className="font-medium text-foreground">
                                                Melebihi
                                                Ekspektasi
                                                Panen
                                            </p>

                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                Maximum
                                                revised
                                                commitment
                                                melebihi
                                                estimasi
                                                maksimum
                                                panen.
                                                Justification
                                                wajib
                                                diisi.
                                            </p>
                                        </div>
                                    </div>
                                )}

                                <div className="grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label
                                            htmlFor="availability_start_at"
                                            className="mb-2 block text-sm font-medium text-foreground"
                                        >
                                            Mulai
                                            Tersedia
                                        </label>

                                        <input
                                            id="availability_start_at"
                                            type="datetime-local"
                                            value={
                                                data.availability_start_at
                                            }
                                            onChange={(
                                                event,
                                            ) =>
                                                setData(
                                                    "availability_start_at",
                                                    event
                                                        .target
                                                        .value,
                                                )
                                            }
                                            className={
                                                inputClassName
                                            }
                                            required
                                        />

                                        <FieldError
                                            message={
                                                errors.availability_start_at
                                            }
                                        />
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="availability_end_at"
                                            className="mb-2 block text-sm font-medium text-foreground"
                                        >
                                            Akhir
                                            Ketersediaan
                                        </label>

                                        <input
                                            id="availability_end_at"
                                            type="datetime-local"
                                            value={
                                                data.availability_end_at
                                            }
                                            onChange={(
                                                event,
                                            ) =>
                                                setData(
                                                    "availability_end_at",
                                                    event
                                                        .target
                                                        .value,
                                                )
                                            }
                                            className={
                                                inputClassName
                                            }
                                            required
                                        />

                                        <FieldError
                                            message={
                                                errors.availability_end_at
                                            }
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label
                                        htmlFor="notes"
                                        className="mb-2 block text-sm font-medium text-foreground"
                                    >
                                        Catatan
                                        Operasional
                                    </label>

                                    <textarea
                                        id="notes"
                                        rows={4}
                                        value={
                                            data.notes
                                        }
                                        onChange={(
                                            event,
                                        ) =>
                                            setData(
                                                "notes",
                                                event
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            textareaClassName
                                        }
                                    />

                                    <FieldError
                                        message={
                                            errors.notes
                                        }
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="change_reason"
                                        className="mb-2 block text-sm font-medium text-foreground"
                                    >
                                        Alasan Revisi
                                    </label>

                                    <textarea
                                        id="change_reason"
                                        rows={4}
                                        value={
                                            data.change_reason
                                        }
                                        onChange={(
                                            event,
                                        ) =>
                                            setData(
                                                "change_reason",
                                                event
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            textareaClassName
                                        }
                                        placeholder="Jelaskan mengapa range atau window commitment harus diperbarui."
                                        required
                                    />

                                    <FieldError
                                        message={
                                            errors.change_reason
                                        }
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="operator_justification"
                                        className="mb-2 block text-sm font-medium text-foreground"
                                    >
                                        Justification
                                        {exceedsExpectedHarvest
                                            ? " *"
                                            : ""}
                                    </label>

                                    <textarea
                                        id="operator_justification"
                                        rows={4}
                                        value={
                                            data.operator_justification
                                        }
                                        onChange={(
                                            event,
                                        ) =>
                                            setData(
                                                "operator_justification",
                                                event
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            textareaClassName
                                        }
                                        placeholder="Jelaskan dasar operasional revised commitment bila diperlukan."
                                        required={
                                            exceedsExpectedHarvest
                                        }
                                    />

                                    <FieldError
                                        message={
                                            errors.operator_justification
                                        }
                                    />

                                    <FieldError
                                        message={
                                            errors.revision
                                        }
                                    />

                                    <FieldError
                                        message={
                                            errors.current_confidence
                                        }
                                    />
                                </div>
                            </CardContent>

                            <CardFooter className="justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(
                                            `/kdkmp/commitments/${commitment.id}`,
                                        )
                                    }
                                >
                                    Batal
                                </Button>

                                <Button
                                    type="submit"
                                    disabled={
                                        processing
                                    }
                                >
                                    {processing && (
                                        <LoaderCircle
                                            data-icon="inline-start"
                                            className="animate-spin"
                                        />
                                    )}

                                    Simpan Draft Revisi
                                </Button>
                            </CardFooter>
                        </Card>
                    </form>
                </div>
            </KdkmpLayout>
        </>
    );
}

function VolumeField({
    id,
    label,
    value,
    symbol,
    onChange,
    error,
}) {
    return (
        <div>
            <label
                htmlFor={id}
                className="mb-2 block text-sm font-medium text-foreground"
            >
                {label}
            </label>

            <div className="relative">
                <input
                    id={id}
                    type="number"
                    min="0"
                    step="any"
                    value={value}
                    onChange={(event) =>
                        onChange(
                            event.target.value,
                        )
                    }
                    className={`${inputClassName} pr-20`}
                    required
                />

                <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                    {symbol}
                </div>
            </div>

            <FieldError message={error} />
        </div>
    );
}

function ContextItem({
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