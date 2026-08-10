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
    "h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15";

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

export default function Edit({
    commitment,
    version,
    isInitialDraft,
    producers = [],
    expectedHarvests = [],
}) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
    } = useForm({
        ...(isInitialDraft
            ? {
                  producer_id:
                      commitment.producer_id ??
                      "",
                  expected_harvest_id:
                      commitment.expected_harvest_id ??
                      "",
              }
            : {}),

        min_volume:
            version.min_volume,

        max_volume:
            version.max_volume,

        unit_id:
            version.unit_id,

        availability_start_at:
            toLocalDateTime(
                version.availability_start_at,
            ),

        availability_end_at:
            toLocalDateTime(
                version.availability_end_at,
            ),

        notes:
            version.notes ?? "",

        change_reason:
            version.change_reason ?? "",

        operator_justification:
            version.operator_justification ??
            "",
    });

    const relevantExpectedHarvests =
        isInitialDraft
            ? expectedHarvests.filter(
                  (harvest) =>
                      String(
                          harvest.producer_id,
                      ) ===
                          String(
                              data.producer_id,
                          ) &&
                      String(
                          harvest.commodity_id,
                      ) ===
                          String(
                              commitment
                                  .forecast
                                  .commodity
                                  .id,
                          ),
              )
            : [];

    const selectedExpectedHarvest =
        relevantExpectedHarvests.find(
            (harvest) =>
                String(harvest.id) ===
                String(
                    data.expected_harvest_id,
                ),
        ) ?? null;

    const exceedsExpectedHarvest =
        Boolean(selectedExpectedHarvest) &&
        Number(data.max_volume) >
            Number(
                selectedExpectedHarvest
                    .expected_max_volume,
            );

    const changeProducer = (value) => {
        setData((current) => ({
            ...current,
            producer_id: value,
            expected_harvest_id: "",
            operator_justification: "",
        }));
    };

    const submit = (event) => {
        event.preventDefault();

        put(
            `/kdkmp/commitments/${commitment.id}/versions/${version.id}`,
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head
                title={`Edit Commitment Version ${version.version_no} — SiagaPasok`}
            />

            <KdkmpLayout
                pageTitle={`Edit Draft Version ${version.version_no}`}
                pageDescription={`${commitment.forecast.forecast_code} • ${commitment.forecast.commodity.name}`}
            >
                <div className="max-w-5xl">
                    <form onSubmit={submit}>
                        <Card>
                            <CardHeader className="border-b">
                                <CardTitle>
                                    Edit Draft
                                    Commitment
                                </CardTitle>

                                <CardDescription>
                                    Hanya version DRAFT
                                    yang dapat diubah.
                                    Approved atau submitted
                                    payload tetap immutable.
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
                                        label="Komoditas"
                                        value={
                                            commitment
                                                .forecast
                                                .commodity
                                                .name
                                        }
                                    />

                                    <ContextItem
                                        label="Unit"
                                        value={`${commitment.forecast.unit.name} (${commitment.forecast.unit.symbol})`}
                                    />

                                    <ContextItem
                                        label="Periode Forecast"
                                        value={formatDateTime(
                                            commitment
                                                .forecast
                                                .required_start_at,
                                        )}
                                        description={`s.d. ${formatDateTime(
                                            commitment
                                                .forecast
                                                .required_end_at,
                                        )}`}
                                    />
                                </div>

                                {isInitialDraft ? (
                                    <section>
                                        <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            Sumber
                                            Pasokan
                                        </p>

                                        <div className="grid gap-5 md:grid-cols-2">
                                            <div>
                                                <label
                                                    htmlFor="producer_id"
                                                    className="mb-2 block text-sm font-medium text-foreground"
                                                >
                                                    Produsen
                                                </label>

                                                <select
                                                    id="producer_id"
                                                    value={
                                                        data.producer_id
                                                    }
                                                    onChange={(
                                                        event,
                                                    ) =>
                                                        changeProducer(
                                                            event
                                                                .target
                                                                .value,
                                                        )
                                                    }
                                                    className={
                                                        inputClassName
                                                    }
                                                    required
                                                >
                                                    <option value="">
                                                        Pilih
                                                        produsen
                                                    </option>

                                                    {producers.map(
                                                        (
                                                            producer,
                                                        ) => (
                                                            <option
                                                                key={
                                                                    producer.id
                                                                }
                                                                value={
                                                                    producer.id
                                                                }
                                                            >
                                                                {
                                                                    producer.name
                                                                }{" "}
                                                                —{" "}
                                                                {
                                                                    producer.producer_code
                                                                }
                                                            </option>
                                                        ),
                                                    )}
                                                </select>

                                                <FieldError
                                                    message={
                                                        errors.producer_id
                                                    }
                                                />
                                            </div>

                                            <div>
                                                <label
                                                    htmlFor="expected_harvest_id"
                                                    className="mb-2 block text-sm font-medium text-foreground"
                                                >
                                                    Ekspektasi
                                                    Panen
                                                </label>

                                                <select
                                                    id="expected_harvest_id"
                                                    value={
                                                        data.expected_harvest_id
                                                    }
                                                    onChange={(
                                                        event,
                                                    ) =>
                                                        setData(
                                                            "expected_harvest_id",
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
                                                        Tidak
                                                        dihubungkan
                                                    </option>

                                                    {relevantExpectedHarvests.map(
                                                        (
                                                            harvest,
                                                        ) => (
                                                            <option
                                                                key={
                                                                    harvest.id
                                                                }
                                                                value={
                                                                    harvest.id
                                                                }
                                                            >
                                                                {formatNumber(
                                                                    harvest.expected_min_volume,
                                                                )}
                                                                –
                                                                {formatNumber(
                                                                    harvest.expected_max_volume,
                                                                )}{" "}
                                                                {
                                                                    harvest.unit_symbol
                                                                }
                                                            </option>
                                                        ),
                                                    )}
                                                </select>

                                                <FieldError
                                                    message={
                                                        errors.expected_harvest_id
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </section>
                                ) : (
                                    <div className="rounded-xl border border-border bg-muted/25 p-4">
                                        <p className="font-medium text-foreground">
                                            Sumber
                                            Commitment
                                            terkunci
                                        </p>

                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            Producer dan
                                            Expected Harvest
                                            merupakan
                                            identitas logical
                                            commitment dan
                                            tidak dapat
                                            diganti pada
                                            revision version.
                                        </p>
                                    </div>
                                )}

                                <div className="grid gap-5 md:grid-cols-2">
                                    <VolumeField
                                        id="min_volume"
                                        label="Minimum Volume"
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
                                        label="Maximum Volume"
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
                                                Range
                                                melebihi
                                                Ekspektasi
                                                Panen
                                            </p>

                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                Ini bukan
                                                hard block,
                                                tetapi
                                                justification
                                                wajib
                                                disertakan.
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

                                {!isInitialDraft && (
                                    <div>
                                        <label
                                            htmlFor="change_reason"
                                            className="mb-2 block text-sm font-medium text-foreground"
                                        >
                                            Alasan
                                            Revisi
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
                                            required
                                        />

                                        <FieldError
                                            message={
                                                errors.change_reason
                                            }
                                        />
                                    </div>
                                )}

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
                                        placeholder="Isi bila diperlukan untuk menjelaskan basis operasional range commitment."
                                        required={
                                            exceedsExpectedHarvest
                                        }
                                    />

                                    <FieldError
                                        message={
                                            errors.operator_justification
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

                                    Simpan Draft
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