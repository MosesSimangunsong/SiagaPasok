import AdminStatusBadge from "@/Components/Admin/AdminStatusBadge";
import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
} from "@/Components/ui/card";
import AdminLayout from "@/Layouts/AdminLayout";
import { Head, Link, router } from "@inertiajs/react";
import { Plus, Users } from "lucide-react";

function formatLastLogin(value) {
    if (!value) {
        return "Belum pernah";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}

export default function Index({ users }) {
    return (
        <>
            <Head title="Pengguna — SiagaPasok" />

            <AdminLayout
                pageTitle="Pengguna"
                pageDescription="Kelola closed account, role, organisasi, dan status pengguna."
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        onClick={() =>
                            router.visit("/admin/users/create")
                        }
                    >
                        <Plus data-icon="inline-start" />
                        Tambah Pengguna
                    </Button>
                }
            >
                <Card>
                    <CardContent className="p-0">
                        {users.length === 0 ? (
                            <div className="flex min-h-64 flex-col items-center justify-center px-6 text-center">
                                <div className="flex size-11 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                    <Users className="size-5" />
                                </div>

                                <h2 className="mt-4 font-semibold text-foreground">
                                    Belum ada pengguna
                                </h2>

                                <p className="mt-1 max-w-sm text-sm leading-6 text-muted-foreground">
                                    Tambahkan pengguna untuk memberikan akses
                                    sesuai role dan organisasi.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[980px] text-left text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Pengguna
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Organisasi
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Role
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Login Terakhir
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
                                        {users.map((user) => (
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
                                                    {user.organization
                                                        ?.name ??
                                                        "Platform"}
                                                </td>

                                                <td className="px-4 py-4 text-muted-foreground">
                                                    {user.role_label}
                                                </td>

                                                <td className="px-4 py-4 text-muted-foreground">
                                                    {formatLastLogin(
                                                        user.last_login_at,
                                                    )}
                                                </td>

                                                <td className="px-4 py-4">
                                                    <AdminStatusBadge
                                                        active={
                                                            user.is_active
                                                        }
                                                    />
                                                </td>

                                                <td className="px-4 py-4 text-right">
                                                    <Link
                                                        href={`/admin/users/${user.id}/edit`}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        Kelola
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
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