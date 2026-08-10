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

export default function ProducerForm({
    producer = null,
}) {
    const isEdit = Boolean(producer);

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
    } = useForm({
        producer_code:
            producer?.producer_code ?? "",

        name:
            producer?.name ?? "",

        village:
            producer?.village ?? "",

        district:
            producer?.district ?? "",

        contact_phone:
            producer?.contact_phone ?? "",

        notes:
            producer?.notes ?? "",
    });

    const submit = (event) => {
        event.preventDefault();

        if (isEdit) {
            put(
                `/kdkmp/producers/${producer.id}`,
                {
                    preserveScroll: true,
                },
            );

            return;
        }

        post("/kdkmp/producers", {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        {isEdit
                            ? "Edit Data Produsen"
                            : "Tambah Produsen"}
                    </CardTitle>

                    <CardDescription>
                        Data ini bersifat internal KDKMP
                        dan tidak ditampilkan kepada SPPG
                        atau KDKMP lain.
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-6">
                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="producer_code"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Kode Produsen
                            </label>

                            <input
                                id="producer_code"
                                value={data.producer_code}
                                onChange={(event) =>
                                    setData(
                                        "producer_code",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                placeholder="Contoh: PRD-001"
                                required
                            />

                            <FieldError
                                message={
                                    errors.producer_code
                                }
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="name"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Nama Produsen
                            </label>

                            <input
                                id="name"
                                value={data.name}
                                onChange={(event) =>
                                    setData(
                                        "name",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                placeholder="Nama produsen"
                                required
                            />

                            <FieldError
                                message={errors.name}
                            />
                        </div>
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="village"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Desa / Kelurahan
                            </label>

                            <input
                                id="village"
                                value={data.village}
                                onChange={(event) =>
                                    setData(
                                        "village",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                placeholder="Nama desa / kelurahan"
                                required
                            />

                            <FieldError
                                message={errors.village}
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="district"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Kecamatan
                            </label>

                            <input
                                id="district"
                                value={data.district}
                                onChange={(event) =>
                                    setData(
                                        "district",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                placeholder="Nama kecamatan"
                                required
                            />

                            <FieldError
                                message={errors.district}
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            htmlFor="contact_phone"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Nomor Kontak
                        </label>

                        <input
                            id="contact_phone"
                            type="tel"
                            value={data.contact_phone}
                            onChange={(event) =>
                                setData(
                                    "contact_phone",
                                    event.target.value,
                                )
                            }
                            className={inputClassName}
                            placeholder="Opsional"
                        />

                        <p className="mt-1.5 text-xs leading-5 text-muted-foreground">
                            Opsional. Kontak hanya untuk
                            koordinasi internal KDKMP.
                        </p>

                        <FieldError
                            message={
                                errors.contact_phone
                            }
                        />
                    </div>

                    <div>
                        <label
                            htmlFor="notes"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Catatan Internal
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
                            placeholder="Catatan opsional"
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
                                    ? `/kdkmp/producers/${producer.id}`
                                    : "/kdkmp/producers",
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
                            : "Tambah Produsen"}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}