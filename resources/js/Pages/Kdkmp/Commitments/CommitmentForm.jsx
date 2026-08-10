import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { router, useForm } from "@inertiajs/react";
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

export default function CommitmentForm({
    forecasts,
    producers,
    expectedHarvests,
    selectedForecastId = null,
}) {
    const initialForecastId =
        selectedForecastId ??
        forecasts[0]?.id ??
        "";

    const initialForecast =
        forecasts.find(
            (forecast) =>
                String(forecast.id) ===
                String(initialForecastId),
        ) ?? forecasts[0];

    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        forecast_id:
            initialForecast?.id ?? "",

        producer_id:
            producers[0]?.id ?? "",

        expected_harvest_id: "",

        min_volume: "",
        max_volume: "",

        unit_id:
            initialForecast?.unit?.id ?? "",

        availability_start_at:
            toLocalDateTime(
                initialForecast?.required_start_at,
            ),

        availability_end_at:
            toLocalDateTime(
                initialForecast?.required_end_at,
            ),

        notes: "",

        operator_justification: "",
    });

    const selectedForecast =
        forecasts.find(
            (forecast) =>
                String(forecast.id) ===
                String(data.forecast_id),
        ) ?? null;

    const relevantExpectedHarvests =
        expectedHarvests.filter(
            (harvest) =>
                String(harvest.producer_id) ===
                    String(data.producer_id) &&
                String(harvest.commodity_id) ===
                    String(
                        selectedForecast
                            ?.commodity?.id,
                    ),
        );

    const selectedExpectedHarvest =
        relevantExpectedHarvests.find(
            (harvest) =>
                String(harvest.id) ===
                String(
                    data.expected_harvest_id,
                ),
        ) ?? null;

    const proposedMaximum =
        Number(data.max_volume);

    const expectedMaximum =
        Number(
            selectedExpectedHarvest
                ?.expected_max_volume,
        );

    const exceedsExpectedHarvest =
        Boolean(selectedExpectedHarvest) &&
        Number.isFinite(proposedMaximum) &&
        Number.isFinite(expectedMaximum) &&
        proposedMaximum > expectedMaximum;

    const changeForecast = (value) => {
        const forecast = forecasts.find(
            (item) =>
                String(item.id) ===
                String(value),
        );

        setData((current) => ({
            ...current,

            forecast_id: value,

            unit_id:
                forecast?.unit?.id ?? "",

            expected_harvest_id: "",

            availability_start_at:
                toLocalDateTime(
                    forecast?.required_start_at,
                ),

            availability_end_at:
                toLocalDateTime(
                    forecast?.required_end_at,
                ),

            operator_justification: "",
        }));
    };

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

        post(
            "/kdkmp/commitments",
            {
                preserveScroll: true,
            },
        );
    };

    const cannotCreate =
        forecasts.length === 0 ||
        producers.length === 0;

    return (
        <form onSubmit={submit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        Draft Komitmen Pasokan
                    </CardTitle>

                    <CardDescription>
                        Catat range pasokan yang dapat
                        disiapkan KDKMP. Commitment baru
                        belum menjadi Pasokan Aman sebelum
                        disetujui Manager.
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-7">
                    {cannotCreate && (
                        <div className="rounded-xl border border-border bg-muted/35 p-4">
                            <p className="font-medium text-foreground">
                                Commitment belum dapat dibuat
                            </p>

                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                Pastikan terdapat Forecast
                                PUBLISHED yang relevan dan
                                minimal satu Produsen aktif.
                            </p>
                        </div>
                    )}

                    <section>
                        <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Forecast Kebutuhan
                        </p>

                        <div>
                            <label
                                htmlFor="forecast_id"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Forecast
                            </label>

                            <select
                                id="forecast_id"
                                value={
                                    data.forecast_id
                                }
                                onChange={(event) =>
                                    changeForecast(
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
                                    Pilih Forecast
                                </option>

                                {forecasts.map(
                                    (forecast) => (
                                        <option
                                            key={
                                                forecast.id
                                            }
                                            value={
                                                forecast.id
                                            }
                                        >
                                            {
                                                forecast.forecast_code
                                            }{" "}
                                            —{" "}
                                            {
                                                forecast
                                                    .commodity
                                                    .name
                                            }{" "}
                                            —{" "}
                                            {
                                                forecast
                                                    .sppg
                                                    .name
                                            }
                                        </option>
                                    ),
                                )}
                            </select>

                            <FieldError
                                message={
                                    errors.forecast_id
                                }
                            />
                        </div>

                        {selectedForecast && (
                            <ForecastContext
                                forecast={
                                    selectedForecast
                                }
                            />
                        )}
                    </section>

                    <div className="border-t border-border" />

                    <section>
                        <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Sumber Pasokan
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
                                        Pilih produsen
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
                                    Ekspektasi Panen
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
                                        Tidak dihubungkan
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
                                                {formatExpectedHarvestOption(
                                                    harvest,
                                                )}
                                            </option>
                                        ),
                                    )}
                                </select>

                                <p className="mt-1.5 text-xs leading-5 text-muted-foreground">
                                    Opsional. Digunakan
                                    sebagai konteks planning,
                                    bukan batas kapasitas
                                    mutlak.
                                </p>

                                <FieldError
                                    message={
                                        errors.expected_harvest_id
                                    }
                                />
                            </div>
                        </div>

                        {selectedExpectedHarvest && (
                            <ExpectedHarvestContext
                                harvest={
                                    selectedExpectedHarvest
                                }
                            />
                        )}
                    </section>

                    <div className="border-t border-border" />

                    <section>
                        <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Range Commitment
                        </p>

                        <div className="grid gap-5 md:grid-cols-2">
                            <VolumeField
                                id="min_volume"
                                label="Minimum Volume"
                                value={
                                    data.min_volume
                                }
                                unit={
                                    selectedForecast
                                        ?.unit
                                }
                                onChange={(value) =>
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
                                unit={
                                    selectedForecast
                                        ?.unit
                                }
                                onChange={(value) =>
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
                            message={errors.unit_id}
                        />

                        {exceedsExpectedHarvest && (
                            <div className="mt-5 flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                                <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-400" />

                                <div>
                                    <p className="font-medium text-foreground">
                                        Melebihi Ekspektasi
                                        Panen
                                    </p>

                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        Maximum Commitment
                                        lebih besar dari
                                        estimasi maksimum
                                        panen yang tercatat.
                                        Commitment tetap dapat
                                        dilanjutkan, tetapi
                                        justification wajib
                                        diisi.
                                    </p>
                                </div>
                            </div>
                        )}
                    </section>

                    <section>
                        <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Window Ketersediaan
                        </p>

                        <div className="grid gap-5 md:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="availability_start_at"
                                    className="mb-2 block text-sm font-medium text-foreground"
                                >
                                    Mulai Tersedia
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
                                    Akhir Ketersediaan
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
                    </section>

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
                            value={data.notes}
                            onChange={(event) =>
                                setData(
                                    "notes",
                                    event.target.value,
                                )
                            }
                            className={
                                textareaClassName
                            }
                            placeholder="Catatan operasional terkait commitment pasokan"
                        />

                        <FieldError
                            message={errors.notes}
                        />
                    </div>

                    {(selectedExpectedHarvest ||
                        errors.operator_justification) && (
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
                                onChange={(event) =>
                                    setData(
                                        "operator_justification",
                                        event.target
                                            .value,
                                    )
                                }
                                className={
                                    textareaClassName
                                }
                                placeholder="Jelaskan dasar operasional jika Commitment melampaui estimasi panen."
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
                    )}
                </CardContent>

                <CardFooter className="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                "/kdkmp/commitments",
                            )
                        }
                    >
                        Batal
                    </Button>

                    <Button
                        type="submit"
                        disabled={
                            processing ||
                            cannotCreate
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
    );
}

function ForecastContext({ forecast }) {
    return (
        <div className="mt-4 grid gap-4 rounded-xl border border-border bg-muted/25 p-4 md:grid-cols-2">
            <ContextItem
                label="SPPG"
                value={forecast.sppg.name}
                description={
                    forecast.sppg.code
                }
            />

            <ContextItem
                label="Komoditas"
                value={forecast.commodity.name}
                description={
                    forecast.commodity.code
                }
            />

            <ContextItem
                label="Target Kebutuhan"
                value={formatVolume(
                    forecast.target_volume,
                    forecast.unit,
                )}
            />

            <ContextItem
                label="Periode Dibutuhkan"
                value={formatDateTime(
                    forecast.required_start_at,
                )}
                description={`s.d. ${formatDateTime(
                    forecast.required_end_at,
                )}`}
            />
        </div>
    );
}

function ExpectedHarvestContext({ harvest }) {
    return (
        <div className="mt-4 grid gap-4 rounded-xl border border-border bg-muted/25 p-4 md:grid-cols-2">
            <ContextItem
                label="Range Ekspektasi"
                value={`${formatNumber(
                    harvest.expected_min_volume,
                )}–${formatNumber(
                    harvest.expected_max_volume,
                )} ${harvest.unit?.symbol ?? ""}`}
            />

            <ContextItem
                label="Window Panen"
                value={formatDate(
                    harvest.harvest_start_at,
                )}
                description={`s.d. ${formatDate(
                    harvest.harvest_end_at,
                )}`}
            />
        </div>
    );
}

function VolumeField({
    id,
    label,
    value,
    unit,
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

                {unit && (
                    <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                        {unit.symbol}
                    </div>
                )}
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

function formatExpectedHarvestOption(
    harvest,
) {
    return [
        `${formatNumber(
            harvest.expected_min_volume,
        )}–${formatNumber(
            harvest.expected_max_volume,
        )} ${harvest.unit?.symbol ?? ""}`,
        `${formatDate(
            harvest.harvest_start_at,
        )}–${formatDate(
            harvest.harvest_end_at,
        )}`,
    ].join(" • ");
}

function formatVolume(value, unit) {
    return `${formatNumber(value)} ${
        unit?.symbol ?? ""
    }`;
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

function formatDate(value) {
    if (!value) {
        return "—";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
    }).format(new Date(value));
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