import AppShell from "@/Layouts/AppShell";
import { Button } from "@/components/ui/button";
import { router, usePage } from "@inertiajs/react";
import {
    Building2,
    CheckCircle2,
    LayoutDashboard,
    LogOut,
    Users,
} from "lucide-react";

function getInitials(name = "") {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join("");
}

export default function AdminLayout({
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
            href: "/admin",
            icon: LayoutDashboard,
            active: currentUrl === "/admin",
        },
        {
            label: "Organisasi",
            href: "/admin/organizations",
            icon: Building2,
            active: currentUrl.startsWith("/admin/organizations"),
        },
        {
            label: "Pengguna",
            href: "/admin/users",
            icon: Users,
            active: currentUrl.startsWith("/admin/users"),
        },
    ];

    const shellUser = auth?.user
        ? {
              name: auth.user.name,
              initials: getInitials(auth.user.name),
              description: auth.user.role_label,
          }
        : null;

    const logout = () => {
        router.post("/logout");
    };

    return (
        <AppShell
            pageTitle={pageTitle}
            pageDescription={pageDescription}
            workspaceLabel="Administrasi Platform"
            navigationLabel="Administrasi"
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