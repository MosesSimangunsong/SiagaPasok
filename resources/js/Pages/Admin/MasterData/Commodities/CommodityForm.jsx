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

export default function CommodityForm({
    commodity = null,
    units,
}) {
    const isEdit = Boolean(commodity);

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
    } = useForm({
        code: commodity?.code ?? "",
        name: commodity?.name ?? "",
        default_unit_id:
            commodity?.default_unit_id ??
            units.find((unit) => unit.is_active)?.id ??
            units[0]?.id ??
            "",
        harvest_behavior:
            commodity?.harvest_behavior ?? "",
        notes: commodity?.notes ?? "",
        is_active: commodity?.is_active ?? true,
    });

    const submit = (event) => {
        event.preventDefault();

        if (isEdit) {
            put(
                `/admin/master-data/commodities/${commodity.id}`,
                {
                    preserveScroll: true,
                },
            );

            return;
        }

        post("/admin/master-data/commodities", {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        {isEdit
                            ? "Informasi Komoditas"
                            : "Komoditas Baru"}
                    </CardTitle>

                    <CardDescription>
                        Commodity master tetap configurable dan
                        tidak di-hard-code pada aplikasi.
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-5 pt-1">
                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="code"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Kode
                            </label>

                            <input
                                id="code"
                                value={data.code}
                                onChange={(event) =>
                                    setData(
                                        "code",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                placeholder="Contoh: KANGKUNG"
                                required
                            />

                            <FieldError message={errors.code} />
                        </div>

                        <div>
                            <label
                                htmlFor="default_unit_id"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Unit Default
                            </label>

                            <select
                                id="default_unit_id"
                                value={data.default_unit_id}
                                onChange={(event) =>
                                    setData(
                                        "default_unit_id",
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
                                        {unit.name} ({unit.symbol})
                                        {!unit.is_active
                                            ? " — Nonaktif"
                                            : ""}
                                    </option>
                                ))}
                            </select>

                            <FieldError
                                message={errors.default_unit_id}
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            htmlFor="name"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Nama Komoditas
                        </label>

                        <input
                            id="name"
                            value={data.name}
                            onChange={(event) =>
                                setData("name", event.target.value)
                            }
                            className={inputClassName}
                            placeholder="Contoh: Kangkung"
                            required
                        />

                        <FieldError message={errors.name} />
                    </div>

                    <div>
                        <label
                            htmlFor="harvest_behavior"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Harvest Behavior
                        </label>

                        <input
                            id="harvest_behavior"
                            value={data.harvest_behavior}
                            onChange={(event) =>
                                setData(
                                    "harvest_behavior",
                                    event.target.value,
                                )
                            }
                            className={inputClassName}
                            placeholder="Opsional, contoh: SINGLE atau RECURRING"
                        />

                        <p className="mt-1.5 text-xs leading-5 text-muted-foreground">
                            Metadata agronomi opsional. Nilai ini
                            bukan penentu Safe Supply.
                        </p>

                        <FieldError
                            message={errors.harvest_behavior}
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
                            value={data.notes}
                            onChange={(event) =>
                                setData(
                                    "notes",
                                    event.target.value,
                                )
                            }
                            rows={4}
                            className="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15"
                            placeholder="Catatan konfigurasi opsional"
                        />

                        <FieldError message={errors.notes} />
                    </div>

                    <div className="rounded-xl border border-border bg-muted/35 p-4">
                        <label className="flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(event) =>
                                    setData(
                                        "is_active",
                                        event.target.checked,
                                    )
                                }
                                className="mt-1 size-4 accent-primary"
                            />

                            <span>
                                <span className="block text-sm font-medium text-foreground">
                                    Komoditas aktif
                                </span>

                                <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                    Komoditas nonaktif tetap
                                    dipertahankan sebagai historical
                                    reference.
                                </span>
                            </span>
                        </label>

                        <FieldError
                            message={errors.is_active}
                        />
                    </div>
                </CardContent>

                <CardFooter className="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            router.visit("/admin/master-data")
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
                            : "Tambah Komoditas"}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}