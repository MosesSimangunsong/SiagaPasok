import { Button } from "@/components/ui/button";
import AppShell from "@/Layouts/AppShell";
import { router, usePage } from "@inertiajs/react";
import {
    Bell,
    CheckCircle2,
    ClipboardList,
    LayoutDashboard,
    LogOut,
} from "lucide-react";

function getInitials(name = "") {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join("");
}

export default function SppgLayout({
    children,
    pageTitle,
    pageDescription,
    headerActions,
}) {
    const page = usePage();
    const { auth, flash } = page.props;

    const currentUrl = page.url;

const navigation = [
    {
        label: "Dashboard",
        href: "/sppg",
        icon: LayoutDashboard,
        active:
            currentUrl ===
            "/sppg",
    },
    {
        label: "Forecast Kebutuhan",
        href: "/sppg/forecasts",
        icon: ClipboardList,
        active:
            currentUrl.startsWith(
                "/sppg/forecasts",
            ) &&
            !currentUrl.includes(
                "/fulfilments",
            ),
    },
    {
        label: "Umpan Balik Pemenuhan",
        href: "/sppg/fulfilments",
        icon: CheckCircle2,
        active:
            currentUrl ===
                "/sppg/fulfilments" ||
            currentUrl.includes(
                "/fulfilments",
            ),
    },
    {
        label: "Notifikasi",
        href: "/notifications",
        icon: Bell,
        active: currentUrl.startsWith(
            "/notifications",
        ),
    },
];

    const organizationName = auth?.user?.organization?.name ?? "SPPG";

    const shellUser = auth?.user
        ? {
              name: auth.user.name,
              initials: getInitials(auth.user.name),
              description: [auth.user.role_label, organizationName]
                  .filter(Boolean)
                  .join(" • "),
          }
        : null;

    const logout = () => {
        router.post("/logout");
    };

    return (
        <AppShell
            pageTitle={pageTitle}
            pageDescription={pageDescription}
            workspaceLabel={organizationName}
            navigationLabel="SPPG"
            navigation={navigation}
            user={shellUser}
            headerActions={
                <>
                    {headerActions}

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={logout}
                    >
                        <LogOut data-icon="inline-start" />
                        Keluar
                    </Button>
                </>
            }
        >
            {flash?.success && (
                <div className="mb-6 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-foreground">
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-primary" />

                    <p>{flash.success}</p>
                </div>
            )}

            {children}
        </AppShell>
    );
}
