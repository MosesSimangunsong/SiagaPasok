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

export default function UserForm({
    user = null,
    organizations,
    roles,
    selectedOrganizationId = null,
}) {
    const isEdit = Boolean(user);

    const initialOrganizationId =
        user?.organization_id ?? selectedOrganizationId ?? "";

    const { data, setData, post, put, processing, errors } = useForm({
        organization_id: initialOrganizationId,
        name: user?.name ?? "",
        email: user?.email ?? "",
        role: user?.role ?? "",
        is_active: user?.is_active ?? true,
        password: "",
        password_confirmation: "",
    });

    const selectedOrganization =
        organizations.find(
            (organization) =>
                String(organization.id) ===
                String(data.organization_id),
        ) ?? null;

    const availableRoles = roles.filter((role) => {
        if (!selectedOrganization) {
            return role.value === "SYSTEM_ADMIN";
        }

        if (
            selectedOrganization.organization_type === "SPPG"
        ) {
            return role.value === "SPPG_USER";
        }

        if (
            selectedOrganization.organization_type === "KDKMP"
        ) {
            return [
                "KDKMP_OPERATOR",
                "KDKMP_MANAGER",
            ].includes(role.value);
        }

        return false;
    });

    const handleOrganizationChange = (value) => {
        setData((current) => {
            const organization =
                organizations.find(
                    (item) => String(item.id) === String(value),
                ) ?? null;

            let nextRole = "";

            if (!organization) {
                nextRole = "SYSTEM_ADMIN";
            } else if (organization.organization_type === "SPPG") {
                nextRole = "SPPG_USER";
            } else if (organization.organization_type === "KDKMP") {
                nextRole = [
                    "KDKMP_OPERATOR",
                    "KDKMP_MANAGER",
                ].includes(current.role)
                    ? current.role
                    : "KDKMP_OPERATOR";
            }

            return {
                ...current,
                organization_id: value,
                role: nextRole,
            };
        });
    };

    const submit = (event) => {
        event.preventDefault();

        if (isEdit) {
            put(`/admin/users/${user.id}`, {
                preserveScroll: true,
            });

            return;
        }

        post("/admin/users", {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        {isEdit
                            ? "Informasi Pengguna"
                            : "Pengguna Baru"}
                    </CardTitle>

                    <CardDescription>
                        {isEdit
                            ? "Kelola identitas, role, organisasi, status akun, dan password."
                            : "Buat closed account baru untuk pengguna SiagaPasok."}
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-5 pt-1">
                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="name"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Nama
                            </label>

                            <input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(event) =>
                                    setData("name", event.target.value)
                                }
                                className={inputClassName}
                                required
                            />

                            <FieldError message={errors.name} />
                        </div>

                        <div>
                            <label
                                htmlFor="email"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Email
                            </label>

                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(event) =>
                                    setData("email", event.target.value)
                                }
                                className={inputClassName}
                                required
                            />

                            <FieldError message={errors.email} />
                        </div>
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="organization_id"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Organisasi
                            </label>

                            <select
                                id="organization_id"
                                value={data.organization_id}
                                onChange={(event) =>
                                    handleOrganizationChange(
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                            >
                                <option value="">
                                    Platform / Tanpa Organisasi
                                </option>

                                {organizations.map((organization) => (
                                    <option
                                        key={organization.id}
                                        value={organization.id}
                                    >
                                        {organization.name} —{" "}
                                        {
                                            organization.organization_type_label
                                        }
                                        {!organization.is_active
                                            ? " (Nonaktif)"
                                            : ""}
                                    </option>
                                ))}
                            </select>

                            <FieldError
                                message={errors.organization_id}
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="role"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Role
                            </label>

                            <select
                                id="role"
                                value={data.role}
                                onChange={(event) =>
                                    setData("role", event.target.value)
                                }
                                className={inputClassName}
                                required
                            >
                                <option value="">Pilih role</option>

                                {availableRoles.map((role) => (
                                    <option
                                        key={role.value}
                                        value={role.value}
                                    >
                                        {role.label}
                                    </option>
                                ))}
                            </select>

                            <FieldError message={errors.role} />
                        </div>
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="password"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                {isEdit
                                    ? "Password Baru"
                                    : "Password Awal"}
                            </label>

                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(event) =>
                                    setData(
                                        "password",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                autoComplete="new-password"
                                required={!isEdit}
                            />

                            {isEdit && (
                                <p className="mt-1.5 text-xs leading-5 text-muted-foreground">
                                    Kosongkan jika password tidak ingin
                                    diubah.
                                </p>
                            )}

                            <FieldError message={errors.password} />
                        </div>

                        <div>
                            <label
                                htmlFor="password_confirmation"
                                className="mb-2 block text-sm font-medium text-foreground"
                            >
                                Konfirmasi Password
                            </label>

                            <input
                                id="password_confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(event) =>
                                    setData(
                                        "password_confirmation",
                                        event.target.value,
                                    )
                                }
                                className={inputClassName}
                                autoComplete="new-password"
                                required={!isEdit}
                            />
                        </div>
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
                                        Akun aktif
                                    </span>

                                    <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                        Akun nonaktif tidak dapat login ke
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
                        onClick={() => router.visit("/admin/users")}
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
                            : "Tambah Pengguna"}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}