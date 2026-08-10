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

export default function UnitForm({ unit = null }) {
    const isEdit = Boolean(unit);

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
    } = useForm({
        code: unit?.code ?? "",
        name: unit?.name ?? "",
        symbol: unit?.symbol ?? "",
        decimal_precision: unit?.decimal_precision ?? 2,
        is_active: unit?.is_active ?? true,
    });

    const submit = (event) => {
        event.preventDefault();

        if (isEdit) {
            put(`/admin/master-data/units/${unit.id}`, {
                preserveScroll: true,
            });

            return;
        }

        post("/admin/master-data/units", {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        {isEdit ? "Informasi Unit" : "Unit Baru"}
                    </CardTitle>

                    <CardDescription>
                        Unit merupakan metadata pengukuran. SiagaPasok
                        tidak melakukan konversi unit otomatis pada MVP.
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
                                placeholder="Contoh: kg"
                                required
                            />

                            <FieldError message={errors.code} />
                        </div>

                        <div>
                            <label
                                htmlFor="symbol"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Simbol
                            </label>

                            <input
                                id="symbol"
                                value={data.symbol}
                                onChange={(event) =>
                                    setData(
                                        "symbol",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                placeholder="Contoh: kg"
                                required
                            />

                            <FieldError
                                message={errors.symbol}
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            htmlFor="name"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Nama Unit
                        </label>

                        <input
                            id="name"
                            value={data.name}
                            onChange={(event) =>
                                setData("name", event.target.value)
                            }
                            className={inputClassName}
                            placeholder="Contoh: Kilogram"
                            required
                        />

                        <FieldError message={errors.name} />
                    </div>

                    <div>
                        <label
                            htmlFor="decimal_precision"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Presisi Desimal
                        </label>

                        <input
                            id="decimal_precision"
                            type="number"
                            min="0"
                            max="6"
                            value={data.decimal_precision}
                            onChange={(event) =>
                                setData(
                                    "decimal_precision",
                                    Number(event.target.value),
                                )
                            }
                            className={inputClassName}
                            required
                        />

                        <FieldError
                            message={errors.decimal_precision}
                        />
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
                                    Unit aktif
                                </span>

                                <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                    Unit nonaktif tetap dipertahankan
                                    sebagai historical reference.
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
                            : "Tambah Unit"}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}