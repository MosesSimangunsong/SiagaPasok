import AdminStatusBadge from "@/Components/Admin/AdminStatusBadge";
import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
} from "@/Components/ui/card";
import AdminLayout from "@/Layouts/AdminLayout";
import { Head, Link, router } from "@inertiajs/react";
import { Building2, Plus } from "lucide-react";

export default function Index({ organizations }) {
    return (
        <>
            <Head title="Organisasi — SiagaPasok" />

            <AdminLayout
                pageTitle="Organisasi"
                pageDescription="Kelola organisasi SPPG dan KDKMP."
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        onClick={() =>
                            router.visit("/admin/organizations/create")
                        }
                    >
                        <Plus data-icon="inline-start" />
                        Tambah Organisasi
                    </Button>
                }
            >
                <Card>
                    <CardContent className="p-0">
                        {organizations.length === 0 ? (
                            <div className="flex min-h-64 flex-col items-center justify-center px-6 text-center">
                                <div className="flex size-11 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                    <Building2 className="size-5" />
                                </div>

                                <h2 className="mt-4 font-semibold text-foreground">
                                    Belum ada organisasi
                                </h2>

                                <p className="mt-1 max-w-sm text-sm leading-6 text-muted-foreground">
                                    Tambahkan SPPG atau KDKMP pertama untuk
                                    memulai konfigurasi SiagaPasok.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[780px] text-left text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Organisasi
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Tipe
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Lokasi
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Pengguna
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
                                        {organizations.map(
                                            (organization) => (
                                                <tr
                                                    key={organization.id}
                                                    className="bg-card"
                                                >
                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                organization.name
                                                            }
                                                        </p>

                                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                                            {
                                                                organization.code
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {
                                                            organization.organization_type_label
                                                        }
                                                    </td>

                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {organization.general_location ??
                                                            "—"}
                                                    </td>

                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {
                                                            organization.users_count
                                                        }
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <AdminStatusBadge
                                                            active={
                                                                organization.is_active
                                                            }
                                                        />
                                                    </td>

                                                    <td className="px-4 py-4 text-right">
                                                        <Link
                                                            href={`/admin/organizations/${organization.id}/edit`}
                                                            className="font-medium text-primary hover:underline"
                                                        >
                                                            Kelola
                                                        </Link>
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
            </AdminLayout>
        </>
    );
}