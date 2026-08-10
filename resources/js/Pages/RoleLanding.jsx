import {
    Head,
    Link,
    router,
} from "@inertiajs/react";
import {
    ArrowRight,
    CheckCircle2,
    LogOut,
    Network,
    ShieldCheck,
} from "lucide-react";

export default function RoleLanding({
    workspace,
    roleLabel,
    description,
    actionLabel = null,
    actionHref = null,
}) {
    const logout = () => {
        router.post("/logout");
    };

    return (
        <>
            <Head title={`${workspace} — SiagaPasok`} />

            <main className="min-h-screen bg-background">
                <header className="border-b border-border bg-card">
                    <div className="mx-auto flex min-h-16 max-w-7xl items-center justify-between px-6">
                        <div className="flex items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <Network className="size-4" />
                            </div>

                            <span className="font-semibold tracking-tight text-brand-navy-950">
                                SiagaPasok
                            </span>
                        </div>

                        <button
                            type="button"
                            onClick={logout}
                            className="inline-flex h-9 items-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-medium text-foreground transition hover:bg-muted"
                        >
                            <LogOut className="size-4" />
                            Keluar
                        </button>
                    </div>
                </header>

                <section className="mx-auto max-w-7xl px-6 py-12">
                    <div className="max-w-3xl">
                        <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3 py-1.5 text-sm font-medium text-primary">
                            <ShieldCheck className="size-4" />
                            {roleLabel}
                        </div>

                        <h1 className="text-3xl font-semibold tracking-[-0.03em] text-foreground sm:text-4xl">
                            {workspace}
                        </h1>

                        <p className="mt-3 max-w-2xl text-base leading-7 text-muted-foreground">
                            {description}
                        </p>
                    </div>

                    <div className="mt-10 max-w-3xl rounded-xl border border-border bg-card p-6">
                        <div className="flex items-start gap-4">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <CheckCircle2 className="size-5 text-primary" />
                            </div>

                            <div className="flex-1">
                                <h2 className="font-semibold text-foreground">
                                    Workspace aktif
                                </h2>

                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                    Authentication,
                                    active-account gate, dan
                                    role-based access aktif.
                                    Gunakan modul yang sudah
                                    tersedia untuk melanjutkan.
                                </p>

                                {actionHref &&
                                actionLabel ? (
                                    <Link
                                        href={actionHref}
                                        className="mt-4 inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                                    >
                                        {actionLabel}

                                        <ArrowRight className="size-4" />
                                    </Link>
                                ) : (
                                    <div className="mt-4 inline-flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                        Modul workspace akan
                                        ditambahkan bertahap.

                                        <ArrowRight className="size-4" />
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}