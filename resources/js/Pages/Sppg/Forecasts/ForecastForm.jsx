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
import { LoaderCircle } from "lucide-react";

const inputClassName =
    "h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15";

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

export default function ForecastForm({
    forecast = null,
    commodities,
    units,
}) {
    const isEdit = Boolean(forecast);

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
    } = useForm({
        commodity_id:
            forecast?.commodity?.id ??
            commodities[0]?.id ??
            "",

        unit_id:
            forecast?.unit?.id ??
            commodities[0]?.default_unit_id ??
            units[0]?.id ??
            "",

        target_volume:
            forecast?.target_volume ?? "",

        required_start_at:
            toLocalDateTime(
                forecast?.required_start_at,
            ),

        required_end_at:
            toLocalDateTime(
                forecast?.required_end_at,
            ),

        freshness_interval_hours:
            forecast?.freshness_interval_hours ?? "",

        notes: forecast?.notes ?? "",

        version: forecast?.version ?? null,
    });

    const selectedUnit = units.find(
        (unit) =>
            String(unit.id) === String(data.unit_id),
    );

    const changeCommodity = (value) => {
        const commodity = commodities.find(
            (item) =>
                String(item.id) === String(value),
        );

        setData((current) => ({
            ...current,
            commodity_id: value,
            unit_id:
                commodity?.default_unit_id ??
                current.unit_id,
        }));
    };

    const submit = (event) => {
        event.preventDefault();

        if (isEdit) {
            put(
                `/sppg/forecasts/${forecast.id}`,
                {
                    preserveScroll: true,
                },
            );

            return;
        }

        post("/sppg/forecasts", {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        {isEdit
                            ? "Edit Draft Forecast"
                            : "Draft Forecast Baru"}
                    </CardTitle>

                    <CardDescription>
                        Masukkan kebutuhan aktual SPPG.
                        Forecast tidak dihitung otomatis dari
                        AKG, gramasi, atau menu.
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-6">
                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="commodity_id"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Komoditas
                            </label>

                            <select
                                id="commodity_id"
                                value={data.commodity_id}
                                onChange={(event) =>
                                    changeCommodity(
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                required
                            >
                                <option value="">
                                    Pilih komoditas
                                </option>

                                {commodities.map(
                                    (commodity) => (
                                        <option
                                            key={commodity.id}
                                            value={commodity.id}
                                        >
                                            {commodity.name}
                                        </option>
                                    ),
                                )}
                            </select>

                            <FieldError
                                message={
                                    errors.commodity_id
                                }
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="unit_id"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Unit
                            </label>

                            <select
                                id="unit_id"
                                value={data.unit_id}
                                onChange={(event) =>
                                    setData(
                                        "unit_id",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                required
                            >
                                <option value="">
                                    Pilih unit
                                </option>

                                {units.map((unit) => (
                                    <option
                                        key={unit.id}
                                        value={unit.id}
                                    >
                                        {unit.name} (
                                        {unit.symbol})
                                    </option>
                                ))}
                            </select>

                            <FieldError
                                message={errors.unit_id}
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            htmlFor="target_volume"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Target Volume
                        </label>

                        <div className="relative">
                            <input
                                id="target_volume"
                                type="number"
                                min="0"
                                step="any"
                                value={data.target_volume}
                                onChange={(event) =>
                                    setData(
                                        "target_volume",
                                        event.target.value,
                                    )
                                }
                                className={`${inputClassName} pr-20`}
                                placeholder="Contoh: 100"
                                required
                            />

                            {selectedUnit && (
                                <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                    {selectedUnit.symbol}
                                </div>
                            )}
                        </div>

                        <FieldError
                            message={errors.target_volume}
                        />
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="required_start_at"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Mulai Dibutuhkan
                            </label>

                            <input
                                id="required_start_at"
                                type="datetime-local"
                                value={
                                    data.required_start_at
                                }
                                onChange={(event) =>
                                    setData(
                                        "required_start_at",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                required
                            />

                            <FieldError
                                message={
                                    errors.required_start_at
                                }
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="required_end_at"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Batas Akhir Kebutuhan
                            </label>

                            <input
                                id="required_end_at"
                                type="datetime-local"
                                value={data.required_end_at}
                                onChange={(event) =>
                                    setData(
                                        "required_end_at",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                required
                            />

                            <FieldError
                                message={
                                    errors.required_end_at
                                }
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            htmlFor="freshness_interval_hours"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Freshness Interval
                        </label>

                        <div className="relative max-w-sm">
                            <input
                                id="freshness_interval_hours"
                                type="number"
                                min="1"
                                value={
                                    data.freshness_interval_hours
                                }
                                onChange={(event) =>
                                    setData(
                                        "freshness_interval_hours",
                                        event.target.value,
                                    )
                                }
                                className={`${inputClassName} pr-20`}
                                placeholder="Opsional"
                            />

                            <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                jam
                            </div>
                        </div>

                        <p className="mt-1.5 text-xs leading-5 text-muted-foreground">
                            Opsional. Digunakan sebagai
                            konfigurasi freshness pasokan untuk
                            Forecast ini.
                        </p>

                        <FieldError
                            message={
                                errors.freshness_interval_hours
                            }
                        />
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
                            value={data.notes}
                            onChange={(event) =>
                                setData(
                                    "notes",
                                    event.target.value,
                                )
                            }
                            className="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15"
                            placeholder="Catatan kebutuhan opsional"
                        />

                        <FieldError
                            message={errors.notes}
                        />
                    </div>

                    <FieldError
                        message={errors.version}
                    />
                </CardContent>

                <CardFooter className="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                isEdit
                                    ? `/sppg/forecasts/${forecast.id}`
                                    : "/sppg/forecasts",
                            )
                        }
                    >
                        Batal
                    </Button>

                    <Button
                        type="submit"
                        disabled={processing}
                    >
                        {processing && (
                            <LoaderCircle
                                data-icon="inline-start"
                                className="animate-spin"
                            />
                        )}

                        {isEdit
                            ? "Simpan Draft"
                            : "Buat Draft"}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}