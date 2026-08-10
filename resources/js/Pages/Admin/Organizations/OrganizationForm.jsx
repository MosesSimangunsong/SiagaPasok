import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { router, useForm } from "@inertiajs/react";
import { LoaderCircle } from "lucide-react";

function FieldError({ message }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1.5 text-sm text-destructive">{message}</p>;
}

const inputClassName =
    "h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15";

export default function OrganizationForm({
    organization = null,
    organizationTypes,
}) {
    const isEdit = Boolean(organization);

    const { data, setData, post, put, processing, errors } = useForm({
        code: organization?.code ?? "",
        name: organization?.name ?? "",
        organization_type: organization?.organization_type ?? "",
        general_location: organization?.general_location ?? "",
        is_active: organization?.is_active ?? true,
    });

    const submit = (event) => {
        event.preventDefault();

        if (isEdit) {
            put(`/admin/organizations/${organization.id}`, {
                preserveScroll: true,
            });

            return;
        }

        post("/admin/organizations", {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        {isEdit
                            ? "Informasi Organisasi"
                            : "Organisasi Baru"}
                    </CardTitle>

                    <CardDescription>
                        {isEdit
                            ? "Perbarui identitas dan status organisasi."
                            : "Tambahkan SPPG atau KDKMP ke dalam SiagaPasok."}
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-5 pt-1">
                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="code"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Kode Organisasi
                            </label>

                            <input
                                id="code"
                                type="text"
                                value={data.code}
                                onChange={(event) =>
                                    setData("code", event.target.value)
                                }
                                className={inputClassName}
                                placeholder="Contoh: SPPG-BDG-01"
                                required
                            />

                            <FieldError message={errors.code} />
                        </div>

                        <div>
                            <label
                                htmlFor="organization_type"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Tipe Organisasi
                            </label>

                            <select
                                id="organization_type"
                                value={data.organization_type}
                                onChange={(event) =>
                                    setData(
                                        "organization_type",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                required
                            >
                                <option value="">
                                    Pilih tipe organisasi
                                </option>

                                {organizationTypes.map((type) => (
                                    <option
                                        key={type.value}
                                        value={type.value}
                                    >
                                        {type.label}
                                    </option>
                                ))}
                            </select>

                            <FieldError
                                message={errors.organization_type}
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            htmlFor="name"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Nama Organisasi
                        </label>

                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(event) =>
                                setData("name", event.target.value)
                            }
                            className={inputClassName}
                            placeholder="Nama lengkap organisasi"
                            required
                        />

                        <FieldError message={errors.name} />
                    </div>

                    <div>
                        <label
                            htmlFor="general_location"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Lokasi Umum
                        </label>

                        <input
                            id="general_location"
                            type="text"
                            value={data.general_location}
                            onChange={(event) =>
                                setData(
                                    "general_location",
                                    event.target.value,
                                )
                            }
                            className={inputClassName}
                            placeholder="Contoh: Badung, Bali"
                        />

                        <p className="mt-1.5 text-xs leading-5 text-muted-foreground">
                            Tidak perlu alamat presisi untuk MVP.
                        </p>

                        <FieldError
                            message={errors.general_location}
                        />
                    </div>

                    {isEdit && (
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
                                    className="mt-1 size-4 rounded border-border accent-primary"
                                />

                                <span>
                                    <span className="block text-sm font-medium text-foreground">
                                        Organisasi aktif
                                    </span>

                                    <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                        Jika dinonaktifkan, business user
                                        organisasi ini tidak dapat mengakses
                                        SiagaPasok.
                                    </span>
                                </span>
                            </label>

                            <FieldError message={errors.is_active} />
                        </div>
                    )}
                </CardContent>

                <CardFooter className="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            router.visit("/admin/organizations")
                        }
                    >
                        Batal
                    </Button>

                    <Button type="submit" disabled={processing}>
                        {processing && (
                            <LoaderCircle
                                data-icon="inline-start"
                                className="animate-spin"
                            />
                        )}

                        {isEdit
                            ? "Simpan Perubahan"
                            : "Tambah Organisasi"}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}