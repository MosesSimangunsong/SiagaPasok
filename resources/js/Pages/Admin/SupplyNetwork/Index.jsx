import AdminStatusBadge from "@/components/admin/AdminStatusBadge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import AdminLayout from "@/Layouts/AdminLayout";
import {
    Head,
    router,
    useForm,
    usePage,
} from "@inertiajs/react";
import {
    ArrowUpRight,
    Building2,
    Link2,
    LoaderCircle,
    Network,
    ShieldCheck,
} from "lucide-react";

const inputClassName =
    "h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary focus:ring-3 focus:ring-primary/15";

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

export default function Index({
    sppgs,
    kdkmps,
    networkRoles,
}) {
    const page = usePage();

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        sppg_organization_id: "",
        kdkmp_organization_id: "",
        network_role: "PRIMARY",
        is_active: true,
    });

    const selectedSppg =
        sppgs.find(
            (sppg) =>
                String(sppg.id) ===
                String(data.sppg_organization_id),
        ) ?? null;

    const existingKdkmpIds = new Set(
        selectedSppg?.links.map((link) =>
            String(link.kdkmp.id),
        ) ?? [],
    );

    const availableKdkmps = kdkmps.filter(
        (kdkmp) =>
            !existingKdkmpIds.has(String(kdkmp.id)),
    );

    const selectedSppgHasActivePrimary =
        selectedSppg?.links.some(
            (link) =>
                link.is_active &&
                link.network_role === "PRIMARY",
        ) ?? false;

    const changeSppg = (value) => {
        const sppg =
            sppgs.find(
                (item) => String(item.id) === String(value),
            ) ?? null;

        const hasPrimary =
            sppg?.links.some(
                (link) =>
                    link.is_active &&
                    link.network_role === "PRIMARY",
            ) ?? false;

        setData((current) => ({
            ...current,
            sppg_organization_id: value,
            kdkmp_organization_id: "",
            network_role: hasPrimary ? "NETWORK" : "PRIMARY",
            is_active: true,
        }));
    };

    const submit = (event) => {
        event.preventDefault();

        post("/admin/supply-network", {
            preserveScroll: true,
            onSuccess: () => {
                reset(
                    "kdkmp_organization_id",
                    "network_role",
                );

                setData((current) => ({
                    ...current,
                    network_role: "NETWORK",
                    is_active: true,
                }));
            },
        });
    };

    const assignPrimary = (link) => {
        router.post(
            `/admin/supply-network/${link.id}/assign-primary`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const setActiveState = (link, isActive) => {
        router.patch(
            `/admin/supply-network/${link.id}/active-state`,
            {
                is_active: isActive,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const globalErrors = page.props.errors ?? {};

    return (
        <>
            <Head title="Supply Network — SiagaPasok" />

            <AdminLayout
                pageTitle="Supply Network"
                pageDescription="Konfigurasi hubungan administratif antara SPPG dan jaringan KDKMP."
            >
                <div className="space-y-6">
                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Tambah Network Link
                            </CardTitle>

                            <CardDescription>
                                PRIMARY digunakan untuk direct supply
                                planning. NETWORK menentukan scope
                                jaringan fallback. Keduanya bukan
                                ranking supplier.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <form
                                onSubmit={submit}
                                className="space-y-5"
                            >
                                <div className="grid gap-5 lg:grid-cols-3">
                                    <div>
                                        <label
                                            htmlFor="sppg_organization_id"
                                            className="mb-2 block text-sm font-medium text-foreground"
                                        >
                                            SPPG
                                        </label>

                                        <select
                                            id="sppg_organization_id"
                                            value={
                                                data.sppg_organization_id
                                            }
                                            onChange={(event) =>
                                                changeSppg(
                                                    event.target.value,
                                                )
                                            }
                                            className={
                                                inputClassName
                                            }
                                            required
                                        >
                                            <option value="">
                                                Pilih SPPG
                                            </option>

                                            {sppgs.map((sppg) => (
                                                <option
                                                    key={sppg.id}
                                                    value={sppg.id}
                                                >
                                                    {sppg.name}
                                                    {!sppg.is_active
                                                        ? " — Nonaktif"
                                                        : ""}
                                                </option>
                                            ))}
                                        </select>

                                        <FieldError
                                            message={
                                                errors.sppg_organization_id
                                            }
                                        />
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="kdkmp_organization_id"
                                            className="mb-2 block text-sm font-medium text-foreground"
                                        >
                                            KDKMP
                                        </label>

                                        <select
                                            id="kdkmp_organization_id"
                                            value={
                                                data.kdkmp_organization_id
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    "kdkmp_organization_id",
                                                    event.target.value,
                                                )
                                            }
                                            className={
                                                inputClassName
                                            }
                                            disabled={
                                                !selectedSppg
                                            }
                                            required
                                        >
                                            <option value="">
                                                Pilih KDKMP
                                            </option>

                                            {availableKdkmps.map(
                                                (kdkmp) => (
                                                    <option
                                                        key={
                                                            kdkmp.id
                                                        }
                                                        value={
                                                            kdkmp.id
                                                        }
                                                    >
                                                        {
                                                            kdkmp.name
                                                        }
                                                        {!kdkmp.is_active
                                                            ? " — Nonaktif"
                                                            : ""}
                                                    </option>
                                                ),
                                            )}
                                        </select>

                                        <FieldError
                                            message={
                                                errors.kdkmp_organization_id
                                            }
                                        />
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="network_role"
                                            className="mb-2 block text-sm font-medium text-foreground"
                                        >
                                            Network Role
                                        </label>

                                        <select
                                            id="network_role"
                                            value={
                                                data.network_role
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    "network_role",
                                                    event.target.value,
                                                )
                                            }
                                            className={
                                                inputClassName
                                            }
                                            required
                                        >
                                            {networkRoles.map(
                                                (role) => (
                                                    <option
                                                        key={
                                                            role.value
                                                        }
                                                        value={
                                                            role.value
                                                        }
                                                    >
                                                        {
                                                            role.label
                                                        }
                                                    </option>
                                                ),
                                            )}
                                        </select>

                                        <FieldError
                                            message={
                                                errors.network_role
                                            }
                                        />
                                    </div>
                                </div>

                                {selectedSppg &&
                                    !selectedSppgHasActivePrimary &&
                                    data.network_role ===
                                        "NETWORK" && (
                                        <div className="rounded-xl border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                                            SPPG ini belum mempunyai
                                            PRIMARY aktif. Backend akan
                                            menolak NETWORK aktif sampai
                                            PRIMARY ditetapkan.
                                        </div>
                                    )}

                                {globalErrors.is_active && (
                                    <FieldError
                                        message={
                                            globalErrors.is_active
                                        }
                                    />
                                )}

                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <label className="flex items-center gap-2 text-sm text-foreground">
                                        <input
                                            type="checkbox"
                                            checked={
                                                data.is_active
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    "is_active",
                                                    event.target.checked,
                                                )
                                            }
                                            className="size-4 accent-primary"
                                        />

                                        Aktifkan link setelah dibuat
                                    </label>

                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            !data.sppg_organization_id ||
                                            !data.kdkmp_organization_id
                                        }
                                    >
                                        {processing && (
                                            <LoaderCircle
                                                data-icon="inline-start"
                                                className="animate-spin"
                                            />
                                        )}

                                        <Link2 data-icon="inline-start" />
                                        Tambah Network Link
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {sppgs.length === 0 ? (
                        <Card>
                            <CardContent>
                                <div className="flex min-h-52 flex-col items-center justify-center text-center">
                                    <Building2 className="size-8 text-muted-foreground" />

                                    <h2 className="mt-4 font-semibold text-foreground">
                                        Belum ada SPPG
                                    </h2>

                                    <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                                        Tambahkan organization bertipe
                                        SPPG terlebih dahulu dari
                                        Administrasi Organisasi.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        sppgs.map((sppg) => {
                            const activePrimary =
                                sppg.links.find(
                                    (link) =>
                                        link.is_active &&
                                        link.network_role ===
                                            "PRIMARY",
                                );

                            return (
                                <Card key={sppg.id}>
                                    <CardHeader className="border-b">
                                        <div className="flex flex-wrap items-start justify-between gap-4">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <CardTitle>
                                                        {sppg.name}
                                                    </CardTitle>

                                                    <AdminStatusBadge
                                                        active={
                                                            sppg.is_active
                                                        }
                                                    />
                                                </div>

                                                <CardDescription>
                                                    {sppg.code}
                                                    {sppg.general_location
                                                        ? ` • ${sppg.general_location}`
                                                        : ""}
                                                </CardDescription>
                                            </div>

                                            <div className="text-right">
                                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                    PRIMARY Aktif
                                                </p>

                                                <p className="mt-1 text-sm font-semibold text-foreground">
                                                    {activePrimary
                                                        ?.kdkmp
                                                        .name ??
                                                        "Belum ditetapkan"}
                                                </p>
                                            </div>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="p-0">
                                        {sppg.links.length === 0 ? (
                                            <div className="px-5 py-8 text-sm text-muted-foreground">
                                                Belum ada KDKMP yang
                                                terhubung ke SPPG ini.
                                            </div>
                                        ) : (
                                            <div className="overflow-x-auto">
                                                <table className="w-full min-w-[980px] text-left text-sm">
                                                    <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                                        <tr>
                                                            <th className="px-4 py-3 font-medium">
                                                                KDKMP
                                                            </th>
                                                            <th className="px-4 py-3 font-medium">
                                                                Role
                                                            </th>
                                                            <th className="px-4 py-3 font-medium">
                                                                Link
                                                            </th>
                                                            <th className="px-4 py-3 font-medium">
                                                                Konfigurasi
                                                            </th>
                                                            <th className="px-4 py-3 text-right font-medium">
                                                                Aksi
                                                            </th>
                                                        </tr>
                                                    </thead>

                                                    <tbody className="divide-y divide-border">
                                                        {sppg.links.map(
                                                            (
                                                                link,
                                                            ) => (
                                                                <tr
                                                                    key={
                                                                        link.id
                                                                    }
                                                                >
                                                                    <td className="px-4 py-4">
                                                                        <p className="font-medium text-foreground">
                                                                            {
                                                                                link
                                                                                    .kdkmp
                                                                                    .name
                                                                            }
                                                                        </p>

                                                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                                                            {
                                                                                link
                                                                                    .kdkmp
                                                                                    .code
                                                                            }
                                                                            {link
                                                                                .kdkmp
                                                                                .general_location
                                                                                ? ` • ${link.kdkmp.general_location}`
                                                                                : ""}
                                                                        </p>
                                                                    </td>

                                                                    <td className="px-4 py-4">
                                                                        <RoleBadge
                                                                            role={
                                                                                link.network_role
                                                                            }
                                                                            label={
                                                                                link.network_role_label
                                                                            }
                                                                        />
                                                                    </td>

                                                                    <td className="px-4 py-4">
                                                                        <AdminStatusBadge
                                                                            active={
                                                                                link.is_active
                                                                            }
                                                                        />
                                                                    </td>

                                                                    <td className="px-4 py-4 text-muted-foreground">
                                                                        <p>
                                                                            {
                                                                                link
                                                                                    .configured_by
                                                                                    .name
                                                                            }
                                                                        </p>

                                                                        <p className="mt-0.5 text-xs">
                                                                            {formatDate(
                                                                                link.updated_at,
                                                                            )}
                                                                        </p>
                                                                    </td>

                                                                    <td className="px-4 py-4">
                                                                        <div className="flex justify-end gap-2">
                                                                            {link.network_role ===
                                                                                "NETWORK" && (
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    variant="outline"
                                                                                    onClick={() =>
                                                                                        assignPrimary(
                                                                                            link,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <ArrowUpRight data-icon="inline-start" />
                                                                                    Jadikan
                                                                                    PRIMARY
                                                                                </Button>
                                                                            )}

                                                                            {link.is_active &&
                                                                                link.network_role ===
                                                                                    "NETWORK" && (
                                                                                    <Button
                                                                                        type="button"
                                                                                        size="sm"
                                                                                        variant="outline"
                                                                                        onClick={() =>
                                                                                            setActiveState(
                                                                                                link,
                                                                                                false,
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        Nonaktifkan
                                                                                    </Button>
                                                                                )}

                                                                            {!link.is_active && (
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    variant="outline"
                                                                                    onClick={() =>
                                                                                        setActiveState(
                                                                                            link,
                                                                                            true,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Aktifkan
                                                                                </Button>
                                                                            )}

                                                                            {link.is_active &&
                                                                                link.network_role ===
                                                                                    "PRIMARY" && (
                                                                                    <span className="inline-flex h-9 items-center px-3 text-xs text-muted-foreground">
                                                                                        Ganti
                                                                                        PRIMARY
                                                                                        melalui
                                                                                        link lain
                                                                                    </span>
                                                                                )}
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })
                    )}

                    <Card>
                        <CardContent>
                            <div className="flex items-start gap-3">
                                <ShieldCheck className="mt-0.5 size-5 shrink-0 text-primary" />

                                <div>
                                    <p className="font-medium text-foreground">
                                        Configuration boundary
                                    </p>

                                    <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                                        Halaman ini hanya menampilkan
                                        metadata organisasi dan network
                                        relationship. Producer,
                                        Expected Harvest, commitment,
                                        volume pasokan, fallback payload,
                                        dan readiness internal KDKMP
                                        tidak ditampilkan kepada System
                                        Admin.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </AdminLayout>
        </>
    );
}

function RoleBadge({ role, label }) {
    const primary = role === "PRIMARY";

    return (
        <span
            className={[
                "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
                primary
                    ? "bg-primary/10 text-primary"
                    : "bg-muted text-muted-foreground",
            ].join(" ")}
        >
            {primary ? (
                <ShieldCheck className="size-3.5" />
            ) : (
                <Network className="size-3.5" />
            )}

            {label}
        </span>
    );
}

function formatDate(value) {
    if (!value) {
        return "—";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}