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
    Building2,
    Database,
    Network,
    ShieldCheck,
    UserCheck,
    Users,
} from "lucide-react";

export default function Dashboard({ stats }) {
    const metrics = [
        {
            label: "Total Organisasi",
            value: stats.organizations,
            icon: Building2,
        },
        {
            label: "Organisasi Aktif",
            value: stats.active_organizations,
            icon: ShieldCheck,
        },
        {
            label: "Total Pengguna",
            value: stats.users,
            icon: Users,
        },
        {
            label: "Pengguna Aktif",
            value: stats.active_users,
            icon: UserCheck,
        },
    ];

    return (
        <>
            <Head title="Dashboard Administrasi — SiagaPasok" />

            <AdminLayout
                pageTitle="Dashboard Administrasi"
                pageDescription="Kelola identitas, master data, dan konfigurasi jaringan SiagaPasok."
            >
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {metrics.map((metric) => {
                            const Icon = metric.icon;

                            return (
                                <Card key={metric.label}>
                                    <CardContent>
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <p className="text-sm text-muted-foreground">
                                                    {metric.label}
                                                </p>

                                                <p className="mt-2 text-3xl font-semibold tracking-tight text-foreground">
                                                    {metric.value}
                                                </p>
                                            </div>

                                            <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <Icon className="size-5" />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Organisasi</CardTitle>

                                <CardDescription>
                                    Kelola organization SPPG dan KDKMP
                                    yang menggunakan SiagaPasok.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    onClick={() =>
                                        router.visit(
                                            "/admin/organizations/create",
                                        )
                                    }
                                >
                                    Tambah Organisasi
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(
                                            "/admin/organizations",
                                        )
                                    }
                                >
                                    Lihat Semua
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Pengguna</CardTitle>

                                <CardDescription>
                                    Buat closed account dan kelola role
                                    serta status akses pengguna.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    onClick={() =>
                                        router.visit(
                                            "/admin/users/create",
                                        )
                                    }
                                >
                                    Tambah Pengguna
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.visit("/admin/users")
                                    }
                                >
                                    Lihat Semua
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <div>
                        <h2 className="text-base font-semibold text-foreground">
                            Konfigurasi Platform
                        </h2>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Master reference dan relationship
                            administratif yang mendukung orchestration.
                        </p>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Database className="size-5" />
                                </div>

                                <CardTitle className="mt-2">
                                    Master Data
                                </CardTitle>

                                <CardDescription>
                                    Kelola unit pengukuran dan commodity
                                    master yang digunakan platform.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(
                                            "/admin/master-data",
                                        )
                                    }
                                >
                                    Buka Master Data
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Network className="size-5" />
                                </div>

                                <CardTitle className="mt-2">
                                    Supply Network
                                </CardTitle>

                                <CardDescription>
                                    Tetapkan PRIMARY KDKMP dan jaringan
                                    fallback untuk setiap SPPG.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(
                                            "/admin/supply-network",
                                        )
                                    }
                                >
                                    Atur Supply Network
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Batas Wewenang Admin
                            </CardTitle>

                            <CardDescription>
                                System Admin hanya mengelola
                                configuration dan identitas platform.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <p className="max-w-3xl text-sm leading-6 text-muted-foreground">
                                Area administrasi tidak menyediakan
                                jalur untuk mengubah forecast,
                                commitment, confidence, fallback,
                                readiness, Safe Supply, atau Ready for
                                Procurement.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </AdminLayout>
        </>
    );
}