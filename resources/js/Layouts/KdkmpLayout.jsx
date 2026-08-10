import { Button } from "@/components/ui/button";
import AppShell from "@/Layouts/AppShell";
import { router, usePage } from "@inertiajs/react";
import {
    Activity,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    Handshake,
    LogOut,
    RefreshCcw,
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

export default function KdkmpLayout({
    children,
    pageTitle,
    pageDescription,
    headerActions,
}) {
    const page = usePage();
    const { auth, flash } = page.props;

    const currentUrl = page.url;

    const organizationName =
        auth?.user?.organization?.name ?? "KDKMP";

    const role =
        auth?.user?.role ?? null;

    const roleLabel =
        auth?.user?.role_label ?? "KDKMP";

    const isOperator =
        role === "KDKMP_OPERATOR";

    const isManager =
        role === "KDKMP_MANAGER";

    const operatorNavigation = [
        {
            label: "Forecast Aktif",
            href: "/kdkmp/forecasts",
            icon: ClipboardList,
            active: currentUrl.startsWith(
                "/kdkmp/forecasts",
            ),
        },
        {
            label: "Produsen",
            href: "/kdkmp/producers",
            icon: Users,
            active: currentUrl.startsWith(
                "/kdkmp/producers",
            ),
        },
        {
            label: "Expected Harvest",
            href: "/kdkmp/expected-harvests",
            icon: CalendarDays,
            active: currentUrl.startsWith(
                "/kdkmp/expected-harvests",
            ),
        },
        {
            label: "Komitmen Pasokan",
            href: "/kdkmp/commitments",
            icon: Handshake,
            active: currentUrl.startsWith(
                "/kdkmp/commitments",
            ),
        },
        {
            label: "Monitoring Confidence",
            href: "/kdkmp/confidence",
            icon: Activity,
            active: currentUrl.startsWith(
                "/kdkmp/confidence",
            ),
        },
    ];

    const managerNavigation = [
        {
            label: "Forecast & Pasokan",
            href: "/kdkmp/forecasts",
            icon: ClipboardList,
            active:
                currentUrl.startsWith(
                    "/kdkmp/forecasts",
                ) ||
                currentUrl.startsWith(
                    "/kdkmp/commitments",
                ),
        },
        {
            label: "Approval Queue",
            href: "/kdkmp/manager/approvals",
            icon: ClipboardCheck,
            active: currentUrl.startsWith(
                "/kdkmp/manager/approvals",
            ),
        },
        {
            label: "Recovery Review",
            href: "/kdkmp/manager/recoveries",
            icon: RefreshCcw,
            active: currentUrl.startsWith(
                "/kdkmp/manager/recoveries",
            ),
        },
    ];

    const navigation =
        isOperator
            ? operatorNavigation
            : isManager
              ? managerNavigation
              : [];

    const shellUser = auth?.user
        ? {
              name: auth.user.name,
              initials: getInitials(
                  auth.user.name,
              ),
              description: [
                  roleLabel,
                  organizationName,
              ]
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
            workspaceLabel={
                organizationName
            }
            navigationLabel={roleLabel}
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