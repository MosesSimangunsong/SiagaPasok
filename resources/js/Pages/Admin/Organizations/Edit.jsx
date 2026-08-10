import AdminStatusBadge from "@/Components/Admin/AdminStatusBadge";
import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import AdminLayout from "@/Layouts/AdminLayout";
import OrganizationForm from "./OrganizationForm";
import { Head, router } from "@inertiajs/react";
import { Plus } from "lucide-react";

export default function Edit({
    organization,
    organizationTypes,
}) {
    return (
        <>
            <Head title={`${organization.name} — SiagaPasok`} />

            <AdminLayout
                pageTitle={organization.name}
                pageDescription={`${organization.code} • ${organization.organization_type_label}`}
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        onClick={() =>
                            router.visit(
                                `/admin/users/create?organization=${organization.id}`,
                            )
                        }
                    >
                        <Plus data-icon="inline-start" />
                        Tambah Pengguna
                    </Button>
                }
            >
                <div className="max-w-5xl space-y-6">
                    <OrganizationForm
                        organization={organization}
                        organizationTypes={organizationTypes}
                    />

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Pengguna Organisasi
                            </CardTitle>
                        </CardHeader>

                        <CardContent className="p-0">
                            {organization.users.length === 0 ? (
                                <div className="px-5 py-8 text-sm text-muted-foreground">
                                    Belum ada pengguna pada organisasi ini.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[680px] text-left text-sm">
                                        <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Pengguna
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Role
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
                                            {organization.users.map(
                                                (user) => (
                                                    <tr key={user.id}>
                                                        <td className="px-4 py-4">
                                                            <p className="font-medium text-foreground">
                                                                {user.name}
                                                            </p>

                                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                                {user.email}
                                                            </p>
                                                        </td>

                                                        <td className="px-4 py-4 text-muted-foreground">
                                                            {
                                                                user.role_label
                                                            }
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <AdminStatusBadge
                                                                active={
                                                                    user.is_active
                                                                }
                                                            />
                                                        </td>

                                                        <td className="px-4 py-4 text-right">
                                                            <button
                                                                type="button"
                                                                className="font-medium text-primary hover:underline"
                                                                onClick={() =>
                                                                    router.visit(
                                                                        `/admin/users/${user.id}/edit`,
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