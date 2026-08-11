import { Button } from "@/components/ui/button";
import AppShell from "@/Layouts/AppShell";
import { router, usePage } from "@inertiajs/react";
import {
    Activity,
    Bell,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    FileCheck2,
    Files,
    Handshake,
    LayoutDashboard,
    LogOut,
    Network,
    RefreshCcw,
    Send,
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
        label: "Dashboard",
        href: "/kdkmp/operator",
        icon: LayoutDashboard,
        active:
            currentUrl ===
            "/kdkmp/operator",
    },
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
    {
        label: "Fallback Request",
        href: "/kdkmp/fallback-requests",
        icon: Send,
        active: currentUrl.startsWith(
            "/kdkmp/fallback-requests",
        ),
    },
    {
        label: "Jaringan Fallback",
        href: "/kdkmp/fallback-network",
        icon: Network,
        active: currentUrl.startsWith(
            "/kdkmp/fallback-network",
        ),
    },
    {
        label: "Fallback Offer",
        href: "/kdkmp/fallback-offers",
        icon: Handshake,
        active: currentUrl.startsWith(
            "/kdkmp/fallback-offers",
        ),
    },
    {
        label: "Readiness",
        href: "/kdkmp/readiness",
        icon: FileCheck2,
        active: currentUrl.startsWith(
            "/kdkmp/readiness",
        ),
    },
{
    label: "Document Records",
    href: "/kdkmp/documents",
    icon: Files,
    active: currentUrl.startsWith(
        "/kdkmp/documents",
    ),
},
{
    label: "Hasil Fulfilment",
    href: "/kdkmp/fulfilments",
    icon: ClipboardCheck,
    active: currentUrl.startsWith(
        "/kdkmp/fulfilments",
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

    const managerNavigation = [
    {
        label: "Dashboard",
        href: "/kdkmp/manager",
        icon: LayoutDashboard,
        active:
            currentUrl ===
            "/kdkmp/manager",
    },
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
    {
        label: "Fallback Request Review",
        href: "/kdkmp/manager/fallback-requests",
        icon: Send,
        active: currentUrl.startsWith(
            "/kdkmp/manager/fallback-requests",
        ),
    },
    {
        label: "Outgoing Offer Review",
        href: "/kdkmp/manager/outgoing-offers",
        icon: Handshake,
        active: currentUrl.startsWith(
            "/kdkmp/manager/outgoing-offers",
        ),
    },
    {
        label: "Incoming Offer Decision",
        href: "/kdkmp/manager/incoming-offers",
        icon: Network,
        active: currentUrl.startsWith(
            "/kdkmp/manager/incoming-offers",
        ),
    },
{
    label: "Readiness Approval",
    href: "/kdkmp/manager/readiness",
    icon: FileCheck2,
    active: currentUrl.startsWith(
        "/kdkmp/manager/readiness",
    ),
},
{
    label: "Hasil Fulfilment",
    href: "/kdkmp/fulfilments",
    icon: ClipboardCheck,
    active: currentUrl.startsWith(
        "/kdkmp/fulfilments",
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