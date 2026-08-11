import { Link, router, usePage } from "@inertiajs/react";
import {
    Bell,
    Menu,
Play,
    UsersRound,
} from "lucide-react";

function SidebarNavigationItem({ item }) {
    const Icon = item.icon;

    const classes = [
        "flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors",
        item.active
            ? "bg-sidebar-accent text-sidebar-accent-foreground"
            : "text-white/65 hover:bg-white/5 hover:text-white",
        item.disabled ? "cursor-not-allowed opacity-40" : "",
    ].join(" ");

    const content = (
        <>
            {Icon && <Icon className="size-4 shrink-0" />}

            <span className="truncate">{item.label}</span>
        </>
    );

    if (!item.href || item.disabled) {
        return (
            <div className={classes} aria-disabled="true">
                {content}
            </div>
        );
    }

    return (
        <Link href={item.href} className={classes}>
            {content}
        </Link>
    );
}

function DemoRoleSwitch({ accounts = [] }) {
    const currentAccount =
        accounts.find((account) => account.current) ?? null;

    const handleChange = (event) => {
        const nextAccount = accounts.find(
            (account) => account.key === event.target.value,
        );

        if (!nextAccount || nextAccount.current) {
            return;
        }

        router.post(nextAccount.href);
    };

    if (accounts.length === 0) {
        return null;
    }

    return (
        <div className="hidden items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-2 py-1 xl:flex">
            <UsersRound
                className="size-3.5 shrink-0 text-violet-600"
                aria-hidden="true"
            />

            <div className="flex items-center gap-2">
                <span className="whitespace-nowrap text-[10px] font-semibold uppercase tracking-[0.08em] text-violet-700">
                    Demo Role Switch
                </span>

                <select
                    value={currentAccount?.key ?? ""}
                    onChange={handleChange}
                    className="h-7 max-w-[220px] rounded-md border border-violet-200 bg-white px-2 text-xs font-medium text-violet-950 outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-200"
                    aria-label="Demo Role Switch"
                >
                    {!currentAccount && (
                        <option value="" disabled>
                            Pilih akun simulasi
                        </option>
                    )}

                    {accounts.map((account) => (
                        <option
                            key={account.key}
                            value={account.key}
                        >
                            {account.label} ·{" "}
                            {account.organization_label}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
}

function DemoScenarioControl({ action }) {
    if (!action?.href) {
        return null;
    }

    const handleApply = () => {
        router.post(
            action.href,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <div className="hidden items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-2 py-1 xl:flex">
            <span className="whitespace-nowrap text-[10px] font-semibold uppercase tracking-[0.08em] text-violet-700">
                Demo Controls
            </span>

            <button
                type="button"
                onClick={handleApply}
                className="flex h-7 items-center gap-1.5 whitespace-nowrap rounded-md border border-violet-200 bg-white px-2.5 text-xs font-semibold text-violet-700 transition-colors hover:bg-violet-100 focus:outline-none focus:ring-2 focus:ring-violet-300"
            >
<Play
    className="size-3.5"
    aria-hidden="true"
/>

                {action.label}
            </button>
        </div>
    );
}

export default function AppShell({
    children,
    pageTitle,
    pageDescription,
    headerActions,
    navigation = [],
    navigationLabel = "Navigasi",
    workspaceLabel,
    user,
}) {
    const page = usePage();

    const demoContext = page.props?.demo ?? {};
    const demoEnabled = Boolean(demoContext.enabled);
    const demoLabel = demoContext.label ?? "SIMULASI";

    const demoAccounts = Array.isArray(demoContext.accounts)
    ? demoContext.accounts
    : [];

const demoAction = demoContext.action ?? null;


    const notificationCenter = page.props?.notification_center ?? {};

    const unreadNotificationCount = Number(
        notificationCenter.unread_count ?? 0,
    );

    const notificationHref = notificationCenter.href ?? "/notifications";

    const notificationCountLabel =
        unreadNotificationCount > 99 ? "99+" : String(unreadNotificationCount);

    const notificationActive = page.url.startsWith("/notifications");
    return (
        <div className="min-h-screen bg-background">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-[248px] flex-col bg-sidebar text-sidebar-foreground lg:flex">
                <div className="flex h-16 shrink-0 items-center border-b border-sidebar-border px-5">
                    <div className="flex min-w-0 items-center gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            <span className="text-sm font-bold">S</span>
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
                                        key={item.href ?? item.label}
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
                                SiagaPasok
                            </p>

                            <p className="mt-1 text-[11px] leading-4 text-white/40">
                                Identitas pengguna tidak tersedia.
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

<div className="ml-4 flex shrink-0 items-center gap-2">
{demoEnabled && demoAction && (
    <DemoScenarioControl
        action={demoAction}
    />
)}

    {demoEnabled && (
        <DemoRoleSwitch
            accounts={demoAccounts}
        />
    )}

    {demoEnabled && (
                            <div
                                className="flex items-center gap-1.5 rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-violet-700"
                                aria-label={`Mode ${demoLabel}`}
                                title="Environment simulasi SiagaPasok"
                            >
                                <span
                                    className="size-1.5 rounded-full bg-violet-500"
                                    aria-hidden="true"
                                />

                                <span>{demoLabel}</span>
                            </div>
                        )}

                        <Link
                            href={notificationHref}
                            className={[
                                "relative flex size-9 items-center justify-center rounded-lg border transition-colors",
                                notificationActive
                                    ? "border-primary/30 bg-primary/10 text-primary"
                                    : "border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground",
                            ].join(" ")}
                            aria-label={
                                unreadNotificationCount > 0
                                    ? `${unreadNotificationCount} notifikasi belum dibaca`
                                    : "Buka notifikasi"
                            }
                            title="Notifikasi"
                        >
                            <Bell className="size-4" />

                            {unreadNotificationCount > 0 && (
                                <span className="absolute -right-1.5 -top-1.5 flex min-w-5 items-center justify-center rounded-full bg-destructive px-1.5 text-[10px] font-semibold leading-5 text-destructive-foreground">
                                    {notificationCountLabel}
                                </span>
                            )}
                        </Link>

                        {headerActions}
                    </div>
                </header>

                <main className="p-6">{children}</main>
            </div>
        </div>
    );
}
