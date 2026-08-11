import { Button } from "@/components/ui/button";
import AdminLayout from "@/Layouts/AdminLayout";
import KdkmpLayout from "@/Layouts/KdkmpLayout";
import SppgLayout from "@/Layouts/SppgLayout";
import {
    Head,
    Link,
    router,
    usePage,
} from "@inertiajs/react";
import {
    BadgeCheck,
    Bell,
    Check,
    CircleAlert,
    ClipboardCheck,
    Clock3,
    FileCheck2,
    Handshake,
    Network,
    TriangleAlert,
} from "lucide-react";

const notificationTypeConfig = {
    APPROVAL_REQUIRED: {
        icon: ClipboardCheck,
    },

    SUPPLY_RISK: {
        icon: TriangleAlert,
    },

    STALE_COMMITMENT: {
        icon: Clock3,
    },

    SHORTFALL: {
        icon: CircleAlert,
    },

    FALLBACK_REQUEST: {
        icon: Network,
    },

    FALLBACK_OFFER_DECISION: {
        icon: Handshake,
    },

    READINESS: {
        icon: FileCheck2,
    },

    RFP: {
        icon: BadgeCheck,
    },
};

const priorityConfig = {
    ACTION: {
        label: "Tindakan",
        badgeClass:
            "border-primary/25 bg-primary/10 text-primary",
        iconClass:
            "border-primary/20 bg-primary/10 text-primary",
    },

    WARNING: {
        label: "Peringatan",
        badgeClass:
            "border-amber-200 bg-amber-50 text-amber-800",
        iconClass:
            "border-amber-200 bg-amber-50 text-amber-700",
    },

    INFORMATION: {
        label: "Informasi",
        badgeClass:
            "border-border bg-muted text-muted-foreground",
        iconClass:
            "border-border bg-muted text-muted-foreground",
    },
};

function resolveLayout(role) {
    if (role === "SYSTEM_ADMIN") {
        return AdminLayout;
    }

    if (role === "SPPG_USER") {
        return SppgLayout;
    }

    return KdkmpLayout;
}

function relativeTime(value) {
    if (!value) {
        return "-";
    }

    const date =
        new Date(value);

    const timestamp =
        date.getTime();

    if (
        Number.isNaN(
            timestamp,
        )
    ) {
        return "-";
    }

    const diffSeconds =
        Math.round(
            (
                timestamp
                - Date.now()
            )
            / 1000,
        );

    const absoluteSeconds =
        Math.abs(
            diffSeconds,
        );

    const formatter =
        new Intl.RelativeTimeFormat(
            "id-ID",
            {
                numeric: "auto",
            },
        );

    if (
        absoluteSeconds
        < 60
    ) {
        return formatter.format(
            diffSeconds,
            "second",
        );
    }

    const diffMinutes =
        Math.round(
            diffSeconds / 60,
        );

    if (
        Math.abs(
            diffMinutes,
        ) < 60
    ) {
        return formatter.format(
            diffMinutes,
            "minute",
        );
    }

    const diffHours =
        Math.round(
            diffMinutes / 60,
        );

    if (
        Math.abs(
            diffHours,
        ) < 24
    ) {
        return formatter.format(
            diffHours,
            "hour",
        );
    }

    const diffDays =
        Math.round(
            diffHours / 24,
        );

    if (
        Math.abs(
            diffDays,
        ) < 30
    ) {
        return formatter.format(
            diffDays,
            "day",
        );
    }

    const diffMonths =
        Math.round(
            diffDays / 30,
        );

    if (
        Math.abs(
            diffMonths,
        ) < 12
    ) {
        return formatter.format(
            diffMonths,
            "month",
        );
    }

    const diffYears =
        Math.round(
            diffDays / 365,
        );

    return formatter.format(
        diffYears,
        "year",
    );
}

function absoluteTime(value) {
    if (!value) {
        return "";
    }

    const date =
        new Date(value);

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
        return "";
    }

    return new Intl.DateTimeFormat(
        "id-ID",
        {
            dateStyle:
                "medium",

            timeStyle:
                "short",
        },
    ).format(
        date,
    );
}

function paginationLabel(
    label,
) {
    if (
        label.includes(
            "Previous",
        )
    ) {
        return "Sebelumnya";
    }

    if (
        label.includes(
            "Next",
        )
    ) {
        return "Berikutnya";
    }

    return label
        .replace(
            "&laquo;",
            "",
        )
        .replace(
            "&raquo;",
            "",
        )
        .trim();
}

function NotificationCard({
    notification,
}) {
    const typeConfig =
        notificationTypeConfig[
            notification.type
        ] ?? {
            icon: Bell,
        };

    const priority =
        priorityConfig[
            notification.priority
        ] ??
        priorityConfig
            .INFORMATION;

    const Icon =
        typeConfig.icon;

    const markRead = () => {
        if (
            notification.is_read
        ) {
            return;
        }

        router.patch(
            `/notifications/${notification.id}/read`,
            {},
            {
                preserveScroll:
                    true,

                preserveState:
                    true,
            },
        );
    };

    const openAction = () => {
        if (
            !notification.action_url
        ) {
            markRead();

            return;
        }

        if (
            notification.is_read
        ) {
            router.visit(
                notification.action_url,
            );

            return;
        }

        /*
         * Acknowledgement dilakukan terlebih
         * dahulu sebelum mengikuti CTA.
         *
         * Operational entity tetap menjadi
         * source of truth pada destination page.
         */
        router.patch(
            `/notifications/${notification.id}/read`,
            {},
            {
                preserveScroll:
                    true,

                onSuccess:
                    () => {
                        router.visit(
                            notification.action_url,
                        );
                    },
            },
        );
    };

    return (
        <article
            className={[
                "rounded-xl border p-5 transition-colors",
                notification.is_read
                    ? "border-border bg-card"
                    : "border-primary/25 bg-primary/[0.035]",
            ].join(" ")}
        >
            <div className="flex items-start gap-4">
                <div
                    className={[
                        "flex size-10 shrink-0 items-center justify-center rounded-lg border",
                        priority.iconClass,
                    ].join(" ")}
                >
                    <Icon className="size-5" />
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span
                            className={[
                                "inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold",
                                priority.badgeClass,
                            ].join(" ")}
                        >
                            {
                                priority.label
                            }
                        </span>

                        <span
                            className={[
                                "inline-flex items-center gap-1 text-xs font-medium",
                                notification.is_read
                                    ? "text-muted-foreground"
                                    : "text-foreground",
                            ].join(" ")}
                        >
                            {notification.is_read ? (
                                <>
                                    <Check className="size-3" />
                                    Sudah dibaca
                                </>
                            ) : (
                                <>
                                    <span className="size-1.5 rounded-full bg-primary" />
                                    Belum dibaca
                                </>
                            )}
                        </span>
                    </div>

                    <h2 className="mt-2 text-sm font-semibold leading-5 text-foreground">
                        {
                            notification.title
                        }
                    </h2>

                    <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                        {
                            notification.message
                        }
                    </p>

                    <p
                        className="mt-3 text-xs text-muted-foreground"
                        title={absoluteTime(
                            notification.created_at,
                        )}
                    >
                        {relativeTime(
                            notification.created_at,
                        )}
                    </p>
                </div>

                <div className="flex shrink-0 flex-col gap-2 sm:flex-row">
                    {!notification.is_read && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={
                                markRead
                            }
                        >
                            <Check data-icon="inline-start" />
                            Tandai dibaca
                        </Button>
                    )}

                    {notification.action_url && (
                        <Button
                            type="button"
                            size="sm"
                            onClick={
                                openAction
                            }
                        >
                            Buka
                        </Button>
                    )}
                </div>
            </div>
        </article>
    );
}

function NotificationPagination({
    links = [],
}) {
    if (
        links.length <= 3
    ) {
        return null;
    }

    return (
        <nav
            className="mt-6 flex flex-wrap items-center justify-center gap-1"
            aria-label="Pagination notifikasi"
        >
            {links.map(
                (
                    link,
                    index,
                ) => {
                    const label =
                        paginationLabel(
                            link.label,
                        );

                    const classes = [
                        "inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm font-medium transition-colors",
                        link.active
                            ? "border-primary bg-primary text-primary-foreground"
                            : "border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground",
                    ].join(" ");

                    if (!link.url) {
                        return (
                            <span
                                key={`${link.label}-${index}`}
                                className={`${classes} cursor-not-allowed opacity-40`}
                                aria-disabled="true"
                            >
                                {
                                    label
                                }
                            </span>
                        );
                    }

                    return (
                        <Link
                            key={`${link.label}-${index}`}
                            href={
                                link.url
                            }
                            preserveScroll
                            className={
                                classes
                            }
                        >
                            {
                                label
                            }
                        </Link>
                    );
                },
            )}
        </nav>
    );
}

export default function NotificationIndex({
    notifications,
}) {
    const page =
        usePage();

    const role =
        page.props?.auth
            ?.user
            ?.role ?? null;

    const Layout =
        resolveLayout(
            role,
        );

    const items =
        notifications?.data ?? [];

    return (
        <>
            <Head title="Notifikasi" />

            <Layout
                pageTitle="Notifikasi"
                pageDescription="Tindakan, risiko, dan perubahan status operasional yang relevan untuk Anda."
            >
                <div className="mx-auto max-w-5xl">
                    {items.length === 0 ? (
                        <div className="rounded-xl border border-border bg-card px-6 py-10 text-center">
                            <div className="mx-auto flex size-11 items-center justify-center rounded-xl border border-border bg-muted text-muted-foreground">
                                <Bell className="size-5" />
                            </div>

                            <h2 className="mt-4 text-sm font-semibold text-foreground">
                                Belum ada notifikasi
                            </h2>

                            <p className="mx-auto mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                                Notification terkait approval,
                                risiko pasokan, fallback,
                                readiness, dan Ready for
                                Procurement akan muncul di sini.
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="mb-4 flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-medium text-foreground">
                                        Notification Center
                                    </p>

                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Urutan terbaru lebih dahulu.
                                    </p>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    {
                                        notifications.total
                                    }{" "}
                                    notifikasi
                                </p>
                            </div>

                            <div className="space-y-3">
                                {items.map(
                                    (
                                        notification,
                                    ) => (
                                        <NotificationCard
                                            key={
                                                notification.id
                                            }
                                            notification={
                                                notification
                                            }
                                        />
                                    ),
                                )}
                            </div>

                            <NotificationPagination
                                links={
                                    notifications.links
                                }
                            />
                        </>
                    )}
                </div>
            </Layout>
        </>
    );
}