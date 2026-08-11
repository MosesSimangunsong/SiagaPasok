import { Head, router } from "@inertiajs/react";

function formatDateTime(value) {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}

function priorityLabel(priority) {
    switch (priority) {
        case "ACTION":
            return "Tindakan";

        case "WARNING":
            return "Peringatan";

        case "INFORMATION":
            return "Informasi";

        default:
            return priority;
    }
}

export default function NotificationIndex({
    notifications,
}) {
    const items =
        notifications?.data ?? [];

    const markRead = (notification) => {
        if (notification.is_read) {
            return;
        }

        router.patch(
            `/notifications/${notification.id}/read`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const openNotification = (notification) => {
        if (!notification.action_url) {
            markRead(notification);

            return;
        }

        if (notification.is_read) {
            router.visit(
                notification.action_url,
            );

            return;
        }

        router.patch(
            `/notifications/${notification.id}/read`,
            {},
            {
                preserveScroll: true,

                onSuccess: () => {
                    router.visit(
                        notification.action_url,
                    );
                },
            },
        );
    };

    return (
        <>
            <Head title="Notifikasi" />

            <main className="min-h-screen bg-slate-50 px-6 py-8">
                <div className="mx-auto max-w-5xl">
                    <div className="mb-6">
                        <p className="text-sm font-medium text-slate-500">
                            Notification Center
                        </p>

                        <h1 className="mt-1 text-2xl font-semibold text-slate-950">
                            Notifikasi
                        </h1>

                        <p className="mt-2 max-w-2xl text-sm text-slate-600">
                            Tinjau perubahan operasional,
                            approval, risiko pasokan, dan
                            status readiness yang memerlukan
                            perhatian Anda.
                        </p>
                    </div>

                    {items.length === 0 ? (
                        <div className="rounded-xl border border-slate-200 bg-white p-8">
                            <p className="font-medium text-slate-900">
                                Belum ada notifikasi.
                            </p>

                            <p className="mt-1 text-sm text-slate-500">
                                Notifikasi operasional akan
                                muncul di sini ketika ada
                                perubahan yang relevan.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {items.map(
                                (notification) => (
                                    <article
                                        key={
                                            notification.id
                                        }
                                        className={[
                                            "rounded-xl border bg-white p-5",
                                            notification.is_read
                                                ? "border-slate-200"
                                                : "border-slate-400",
                                        ].join(" ")}
                                    >
                                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        {priorityLabel(
                                                            notification.priority,
                                                        )}
                                                    </span>

                                                    {!notification.is_read && (
                                                        <span className="rounded-full bg-slate-900 px-2 py-0.5 text-xs font-medium text-white">
                                                            Baru
                                                        </span>
                                                    )}
                                                </div>

                                                <h2 className="mt-2 font-semibold text-slate-950">
                                                    {
                                                        notification.title
                                                    }
                                                </h2>

                                                <p className="mt-1 text-sm leading-6 text-slate-600">
                                                    {
                                                        notification.message
                                                    }
                                                </p>

                                                <p className="mt-3 text-xs text-slate-500">
                                                    {formatDateTime(
                                                        notification.created_at,
                                                    )}
                                                </p>
                                            </div>

                                            <div className="flex shrink-0 gap-2">
                                                {!notification.is_read && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            markRead(
                                                                notification,
                                                            )
                                                        }
                                                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                                    >
                                                        Tandai dibaca
                                                    </button>
                                                )}

                                                {notification.action_url && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openNotification(
                                                                notification,
                                                            )
                                                        }
                                                        className="rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800"
                                                    >
                                                        Buka
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </article>
                                ),
                            )}
                        </div>
                    )}
                </div>
            </main>
        </>
    );
}