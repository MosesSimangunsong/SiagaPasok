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
import { Head, router } from "@inertiajs/react";
import {
    Box,
    PackageOpen,
    Plus,
    Ruler,
} from "lucide-react";

export default function Index({ units, commodities }) {
    return (
        <>
            <Head title="Master Data — SiagaPasok" />

            <AdminLayout
                pageTitle="Master Data"
                pageDescription="Kelola unit pengukuran dan komoditas SiagaPasok."
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardContent>
                                <div className="flex items-center gap-4">
                                    <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                        <Ruler className="size-5" />
                                    </div>

                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Unit
                                        </p>

                                        <p className="mt-1 text-2xl font-semibold text-foreground">
                                            {units.length}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div className="flex items-center gap-4">
                                    <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                        <PackageOpen className="size-5" />
                                    </div>

                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Komoditas
                                        </p>

                                        <p className="mt-1 text-2xl font-semibold text-foreground">
                                            {commodities.length}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader className="border-b">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>Unit Pengukuran</CardTitle>

                                    <CardDescription>
                                        Unit digunakan sebagai referensi
                                        pengukuran komoditas.
                                    </CardDescription>
                                </div>

                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() =>
                                        router.visit(
                                            "/admin/master-data/units/create",
                                        )
                                    }
                                >
                                    <Plus data-icon="inline-start" />
                                    Tambah Unit
                                </Button>
                            </div>
                        </CardHeader>

                        <CardContent className="p-0">
                            {units.length === 0 ? (
                                <EmptyState
                                    icon={Ruler}
                                    title="Belum ada unit"
                                    description="Tambahkan unit pengukuran pertama."
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[760px] text-left text-sm">
                                        <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Unit
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Simbol
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Presisi
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Komoditas
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {units.map((unit) => (
                                                <tr key={unit.id}>
                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {unit.name}
                                                        </p>

                                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                                            {unit.code}
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {unit.symbol}
                                                    </td>

                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {
                                                            unit.decimal_precision
                                                        }{" "}
                                                        desimal
                                                    </td>

                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {
                                                            unit.commodities_count
                                                        }
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <AdminStatusBadge
                                                            active={
                                                                unit.is_active
                                                            }
                                                        />
                                                    </td>

                                                    <td className="px-4 py-4 text-right">
                                                        <button
                                                            type="button"
                                                            className="font-medium text-primary hover:underline"
                                                            onClick={() =>
                                                                router.visit(
                                                                    `/admin/master-data/units/${unit.id}/edit`,
                                                                )
                                                            }
                                                        >
                                                            Kelola
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>Komoditas</CardTitle>

                                    <CardDescription>
                                        Daftar komoditas configurable yang
                                        digunakan dalam forecast dan supply.
                                    </CardDescription>
                                </div>

                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() =>
                                        router.visit(
                                            "/admin/master-data/commodities/create",
                                        )
                                    }
                                >
                                    <Plus data-icon="inline-start" />
                                    Tambah Komoditas
                                </Button>
                            </div>
                        </CardHeader>

                        <CardContent className="p-0">
                            {commodities.length === 0 ? (
                                <EmptyState
                                    icon={PackageOpen}
                                    title="Belum ada komoditas"
                                    description="Tambahkan commodity master pertama."
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[900px] text-left text-sm">
                                        <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Komoditas
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Unit Default
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Harvest Behavior
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {commodities.map(
                                                (commodity) => (
                                                    <tr
                                                        key={commodity.id}
                                                    >
                                                        <td className="px-4 py-4">
                                                            <p className="font-medium text-foreground">
                                                                {
                                                                    commodity.name
                                                                }
                                                            </p>

                                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                                {
                                                                    commodity.code
                                                                }
                                                            </p>
                                                        </td>

                                                        <td className="px-4 py-4 text-muted-foreground">
                                                            {
                                                                commodity
                                                                    .default_unit
                                                                    .name
                                                            }{" "}
                                                            (
                                                            {
                                                                commodity
                                                                    .default_unit
                                                                    .symbol
                                                            }
                                                            )
                                                        </td>

                                                        <td className="px-4 py-4 text-muted-foreground">
                                                            {commodity.harvest_behavior ??
                                                                "—"}
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <AdminStatusBadge
                                                                active={
                                                                    commodity.is_active
                                                                }
                                                            />
                                                        </td>

                                                        <td className="px-4 py-4 text-right">
                                                            <button
                                                                type="button"
                                                                className="font-medium text-primary hover:underline"
                                                                onClick={() =>
                                                                    router.visit(
                                                                        `/admin/master-data/commodities/${commodity.id}/edit`,
                                                                    )
                                                                }
                                                            >
                                                                Kelola
                                                            </button>
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
                </div>
            </AdminLayout>
        </>
    );
}

function EmptyState({
    icon: Icon = Box,
    title,
    description,
}) {
    return (
        <div className="flex min-h-48 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-11 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                <Icon className="size-5" />
            </div>

            <h3 className="mt-4 font-semibold text-foreground">
                {title}
            </h3>

            <p className="mt-1 text-sm text-muted-foreground">
                {description}
            </p>
        </div>
    );
}