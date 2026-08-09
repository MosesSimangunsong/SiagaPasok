import { Menu } from 'lucide-react';

function SidebarNavigationItem({ item }) {
    const Icon = item.icon;

    return (
        <div
            className={[
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                item.active
                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                    : 'text-white/65 hover:bg-white/5 hover:text-white',
            ].join(' ')}
        >
            {Icon && (
                <Icon className="size-4 shrink-0" />
            )}

            <span className="truncate">
                {item.label}
            </span>
        </div>
    );
}

export default function AppShell({
    children,
    pageTitle,
    pageDescription,
    headerActions,
    navigation = [],
    navigationLabel = 'Navigasi',
    workspaceLabel,
    user,
}) {
    return (
        <div className="min-h-screen bg-background">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-[248px] flex-col bg-sidebar text-sidebar-foreground lg:flex">
                <div className="flex h-16 shrink-0 items-center border-b border-sidebar-border px-5">
                    <div className="flex min-w-0 items-center gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            <span className="text-sm font-bold">
                                S
                            </span>
                        </div>

                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-white">
                                SiagaPasok
                            </p>

                            <p className="truncate text-xs text-white/55">
                                Pasokan Lokal Siap
                            </p>
                        </div>
                    </div>
                </div>

                {workspaceLabel && (
                    <div className="border-b border-sidebar-border px-5 py-4">
                        <p className="text-[11px] font-medium uppercase tracking-wider text-white/35">
                            Workspace
                        </p>

                        <p className="mt-1 truncate text-sm font-medium text-white/80">
                            {workspaceLabel}
                        </p>
                    </div>
                )}

                <nav className="flex-1 overflow-y-auto px-3 py-4">
                    {navigation.length > 0 && (
                        <>
                            <div className="mb-2 px-3">
                                <p className="text-[11px] font-semibold uppercase tracking-wider text-white/35">
                                    {navigationLabel}
                                </p>
                            </div>

                            <div className="space-y-1">
                                {navigation.map((item) => (
                                    <SidebarNavigationItem
                                        key={item.label}
                                        item={item}
                                    />
                                ))}
                            </div>
                        </>
                    )}
                </nav>

                <div className="border-t border-sidebar-border p-4">
                    {user ? (
                        <div className="flex items-center gap-3 rounded-lg px-2 py-2">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-white">
                                {user.initials}
                            </div>

                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-white/90">
                                    {user.name}
                                </p>

                                {user.description && (
                                    <p className="mt-0.5 truncate text-xs text-white/45">
                                        {user.description}
                                    </p>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="rounded-lg bg-white/5 px-3 py-3">
                            <p className="text-xs font-medium text-white/75">
                                M00 Foundation
                            </p>

                            <p className="mt-1 text-[11px] leading-4 text-white/40">
                                Identity belum diaktifkan.
                            </p>
                        </div>
                    )}
                </div>
            </aside>

            <div className="min-h-screen lg:pl-[248px]">
                <header className="sticky top-0 z-30 flex h-16 items-center border-b border-border bg-card/95 px-6 backdrop-blur">
                    <div className="flex min-w-0 flex-1 items-center gap-4">
                        <button
                            type="button"
                            className="flex size-9 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground lg:hidden"
                            aria-label="Buka navigasi"
                        >
                            <Menu className="size-4" />
                        </button>

                        <div className="flex min-w-0 items-center gap-3 lg:hidden">
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-navy-950 text-xs font-bold text-white">
                                S
                            </div>
                        </div>

                        <div className="min-w-0">
                            <h1 className="truncate text-base font-semibold text-foreground">
                                {pageTitle}
                            </h1>

                            {pageDescription && (
                                <p className="mt-0.5 hidden truncate text-xs text-muted-foreground sm:block">
                                    {pageDescription}
                                </p>
                            )}
                        </div>
                    </div>

                    {headerActions && (
                        <div className="ml-4 flex shrink-0 items-center gap-2">
                            {headerActions}
                        </div>
                    )}
                </header>

                <main className="p-6">
                    {children}
                </main>
            </div>
        </div>
    );
}