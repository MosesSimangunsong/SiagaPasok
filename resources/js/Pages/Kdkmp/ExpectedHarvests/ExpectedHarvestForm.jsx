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

export default function ExpectedHarvestForm({
    expectedHarvest = null,
    producers,
    commodities,
    units,
    selectedProducerId = null,
}) {
    const isEdit = Boolean(expectedHarvest);

    const initialProducerId =
        expectedHarvest?.producer?.id ??
        selectedProducerId ??
        producers[0]?.id ??
        "";

    const initialCommodityId =
        expectedHarvest?.commodity?.id ??
        commodities[0]?.id ??
        "";

    const initialCommodity =
        commodities.find(
            (commodity) =>
                String(commodity.id) ===
                String(initialCommodityId),
        );

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
    } = useForm({
        producer_id:
            initialProducerId,

        commodity_id:
            initialCommodityId,

        unit_id:
            expectedHarvest?.unit?.id ??
            initialCommodity?.default_unit_id ??
            units[0]?.id ??
            "",

        expected_min_volume:
            expectedHarvest?.expected_min_volume ??
            "",

        expected_max_volume:
            expectedHarvest?.expected_max_volume ??
            "",

        harvest_start_at:
            toLocalDateTime(
                expectedHarvest?.harvest_start_at,
            ),

        harvest_end_at:
            toLocalDateTime(
                expectedHarvest?.harvest_end_at,
            ),

        notes:
            expectedHarvest?.notes ?? "",
    });

    const selectedUnit = units.find(
        (unit) =>
            String(unit.id) ===
            String(data.unit_id),
    );

    const changeCommodity = (value) => {
        const commodity = commodities.find(
            (item) =>
                String(item.id) ===
                String(value),
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
                `/kdkmp/expected-harvests/${expectedHarvest.id}`,
                {
                    preserveScroll: true,
                },
            );

            return;
        }

        post(
            "/kdkmp/expected-harvests",
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <form onSubmit={submit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        {isEdit
                            ? "Edit Expected Harvest"
                            : "Catat Expected Harvest"}
                    </CardTitle>

                    <CardDescription>
                        Masukkan estimasi manual range
                        panen. Data ini bukan komitmen dan
                        tidak masuk Safe Supply.
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-6">
                    <div>
                        <label
                            htmlFor="producer_id"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Produsen
                        </label>

                        <select
                            id="producer_id"
                            value={data.producer_id}
                            onChange={(event) =>
                                setData(
                                    "producer_id",
                                    event.target.value,
                                )
                            }
                            className={inputClassName}
                            required
                        >
                            <option value="">
                                Pilih produsen
                            </option>

                            {producers.map(
                                (producer) => (
                                    <option
                                        key={producer.id}
                                        value={producer.id}
                                    >
                                        {producer.name} —{" "}
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
                                value={
                                    data.commodity_id
                                }
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
                                            {
                                                commodity.name
                                            }
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

                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="expected_min_volume"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Estimasi Minimum
                            </label>

                            <div className="relative">
                                <input
                                    id="expected_min_volume"
                                    type="number"
                                    min="0"
                                    step="any"
                                    value={
                                        data.expected_min_volume
                                    }
                                    onChange={(event) =>
                                        setData(
                                            "expected_min_volume",
                                            event.target.value,
                                        )
                                    }
                                    className={`${inputClassName} pr-20`}
                                    required
                                />

                                {selectedUnit && (
                                    <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                        {
                                            selectedUnit.symbol
                                        }
                                    </div>
                                )}
                            </div>

                            <FieldError
                                message={
                                    errors.expected_min_volume
                                }
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="expected_max_volume"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Estimasi Maksimum
                            </label>

                            <div className="relative">
                                <input
                                    id="expected_max_volume"
                                    type="number"
                                    min="0"
                                    step="any"
                                    value={
                                        data.expected_max_volume
                                    }
                                    onChange={(event) =>
                                        setData(
                                            "expected_max_volume",
                                            event.target.value,
                                        )
                                    }
                                    className={`${inputClassName} pr-20`}
                                    required
                                />

                                {selectedUnit && (
                                    <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                        {
                                            selectedUnit.symbol
                                        }
                                    </div>
                                )}
                            </div>

                            <FieldError
                                message={
                                    errors.expected_max_volume
                                }
                            />
                        </div>
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="harvest_start_at"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Mulai Window Panen
                            </label>

                            <input
                                id="harvest_start_at"
                                type="datetime-local"
                                value={
                                    data.harvest_start_at
                                }
                                onChange={(event) =>
                                    setData(
                                        "harvest_start_at",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                required
                            />

                            <FieldError
                                message={
                                    errors.harvest_start_at
                                }
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="harvest_end_at"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Akhir Window Panen
                            </label>

                            <input
                                id="harvest_end_at"
                                type="datetime-local"
                                value={
                                    data.harvest_end_at
                                }
                                onChange={(event) =>
                                    setData(
                                        "harvest_end_at",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                required
                            />

                            <FieldError
                                message={
                                    errors.harvest_end_at
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
                            value={data.notes}
                            onChange={(event) =>
                                setData(
                                    "notes",
                                    event.target.value,
                                )
                            }
                            className="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15"
                            placeholder="Catatan kondisi atau sumber pembaruan estimasi"
                        />

                        <FieldError
                            message={errors.notes}
                        />
                    </div>
                </CardContent>

                <CardFooter className="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                isEdit
                                    ? `/kdkmp/expected-harvests/${expectedHarvest.id}`
                                    : "/kdkmp/expected-harvests",
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
                            ? "Simpan Perubahan"
                            : "Simpan Expected Harvest"}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}