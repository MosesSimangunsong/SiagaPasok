import { Head } from "@inertiajs/react";
import {
    CircleAlert,
    CircleCheck,
    Palette,
    ShieldCheck,
    TriangleAlert,
} from "lucide-react";

import {
    Blocks,
    CircleAlert,
    CircleCheck,
    Palette,
    ShieldCheck,
    TriangleAlert,
} from "lucide-react";

import AppShell from "@/Layouts/AppShell";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

export default function Foundation() {
    return (
        <>
            <Head title="SiagaPasok — UI Foundation" />

            <AppShell
                pageTitle="UI Foundation"
                pageDescription="Fondasi visual dan technical shell SiagaPasok"
                workspaceLabel="Technical Foundation"
                navigationLabel="Foundation"
                navigation={[
                    {
                        label: "UI Foundation",
                        icon: Blocks,
                        active: true,
                    },
                ]}
                headerActions={
                    <Button variant="outline" size="sm">
                        M00
                    </Button>
                }
            >
                <div className="mx-auto max-w-6xl">
                    <div className="max-w-2xl">
                        <div className="mb-4 flex size-10 items-center justify-center rounded-lg bg-brand-blue-50 text-brand-blue-600">
                            <Palette className="size-5" />
                        </div>

                        <p className="text-sm font-semibold text-brand-blue-600">
                            Design System Preview
                        </p>

                        <h2 className="mt-2 text-2xl font-semibold tracking-tight text-foreground">
                            Fondasi visual SiagaPasok
                        </h2>

                        <p className="mt-3 leading-6 text-muted-foreground">
                            Preview teknis untuk memastikan AppShell, shadcn/ui,
                            typography, iconography, dan design tokens dapat
                            digunakan secara konsisten.
                        </p>
                    </div>

                    <div className="mt-8 grid gap-6 xl:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardDescription>
                                    Brand & Interaction
                                </CardDescription>

                                <CardTitle>Deep Navy + Cobalt</CardTitle>
                            </CardHeader>

                            <CardContent className="space-y-5">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="rounded-xl bg-brand-navy-950 p-4 text-white">
                                        <p className="font-medium">Deep Navy</p>

                                        <p className="mt-1 text-xs text-white/60">
                                            #0B1F35
                                        </p>
                                    </div>

                                    <div className="rounded-xl bg-brand-blue-600 p-4 text-white">
                                        <p className="font-medium">Cobalt</p>

                                        <p className="mt-1 text-xs text-white/75">
                                            #2563EB
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <p className="mb-3 font-medium text-foreground">
                                        Button primitives
                                    </p>

                                    <div className="flex flex-wrap gap-3">
                                        <Button>Primary Action</Button>

                                        <Button variant="outline">
                                            Secondary Action
                                        </Button>

                                        <Button variant="ghost">
                                            Ghost Action
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>

                            <CardFooter>
                                <p className="text-xs leading-5 text-muted-foreground">
                                    Cobalt digunakan untuk aksi utama dan state
                                    interaktif, bukan indikator kondisi pasokan.
                                </p>
                            </CardFooter>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardDescription>
                                    Domain Semantics
                                </CardDescription>

                                <CardTitle>Status pasokan</CardTitle>
                            </CardHeader>

                            <CardContent className="space-y-3">
                                <div className="flex items-start gap-3 rounded-xl border border-safe-border bg-safe-background p-4">
                                    <CircleCheck className="mt-0.5 size-5 shrink-0 text-safe-foreground" />

                                    <div>
                                        <p className="font-medium text-safe-foreground">
                                            Aman
                                        </p>

                                        <p className="mt-1 text-sm text-safe-foreground/80">
                                            Kondisi berada dalam batas aman.
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3 rounded-xl border border-risk-border bg-risk-background p-4">
                                    <TriangleAlert className="mt-0.5 size-5 shrink-0 text-risk-foreground" />

                                    <div>
                                        <p className="font-medium text-risk-foreground">
                                            Berisiko
                                        </p>

                                        <p className="mt-1 text-sm text-risk-foreground/80">
                                            Kondisi memerlukan perhatian atau
                                            mitigasi.
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3 rounded-xl border border-critical-border bg-critical-background p-4">
                                    <CircleAlert className="mt-0.5 size-5 shrink-0 text-critical-foreground" />

                                    <div>
                                        <p className="font-medium text-critical-foreground">
                                            Kritis
                                        </p>

                                        <p className="mt-1 text-sm text-critical-foreground/80">
                                            Kondisi membutuhkan tindakan segera.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="mt-6">
                        <CardContent className="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-4">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-blue-50 text-brand-blue-600">
                                    <ShieldCheck className="size-5" />
                                </div>

                                <div>
                                    <p className="font-semibold text-foreground">
                                        AppShell aktif
                                    </p>

                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Layout ini akan menjadi kerangka
                                        reusable untuk halaman aplikasi pada
                                        modul berikutnya.
                                    </p>
                                </div>
                            </div>

                            <div className="shrink-0 rounded-lg border border-border bg-muted px-3 py-2 text-xs font-medium text-muted-foreground">
                                Laravel + Inertia + React
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </AppShell>
        </>
    );
}
