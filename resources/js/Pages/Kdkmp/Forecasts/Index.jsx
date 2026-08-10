import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import KdkmpLayout from "@/Layouts/KdkmpLayout";
import { Head, router } from "@inertiajs/react";
import {
    CalendarDays,
    ClipboardList,
    Eye,
} from "lucide-react";

export default function Index({ forecasts }) {
    return (
        <>
            <Head title="Forecast PUBLISHED — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Forecast PUBLISHED"
                pageDescription="Kebutuhan SPPG yang tersedia untuk direct supply planning KDKMP."
            >
                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>
                            Forecast Kebutuhan SPPG
                        </CardTitle>

                        <CardDescription>
                            Hanya Forecast PUBLISHED dari
                            SPPG yang menempatkan KDKMP Anda
                            sebagai PRIMARY aktif yang
                            ditampilkan.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                        {forecasts.length === 0 ? (
                            <EmptyState />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1100px] text-left text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Forecast
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                SPPG
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Komoditas
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Target
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Periode
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Status
                                            </th>

                                            <th className="px-4 py-3 text-right font-medium">
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {forecasts.map(
                                            (forecast) => (
                                                <tr
                                                    key={
                                                        forecast.id
                                                    }
                                                >
                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                forecast.forecast_code
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            Dipublikasikan{" "}
                                                            {formatDateTime(
                                                                forecast.published_at,
                                                            )}
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                forecast
                                                                    .sppg
                                                                    .name
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                forecast
                                                                    .sppg
                                                                    .code
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                forecast
                                                                    .commodity
                                                                    .name
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                forecast
                                                                    .commodity
                                                                    .code
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4 font-medium text-foreground">
                                                        {formatVolume(
                                                            forecast,
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <div className="flex gap-2 text-muted-foreground">
                                                            <CalendarDays className="mt-0.5 size-4 shrink-0" />

                                                            <div>
                                                                <p>
                                                                    {formatDateTime(
                                                                        forecast.required_start_at,
                                                                    )}
                                                                </p>

                                                                <p className="mt-1 text-xs">
                                                                    s.d.{" "}
                                                                    {formatDateTime(
                                                                        forecast.required_end_at,
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <PublishedBadge />
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <div className="flex justify-end">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    router.visit(
                                                                        `/kdkmp/forecasts/${forecast.id}`,
                                                                    )
                                                                }
                                                            >
                                                                <Eye data-icon="inline-start" />
                                                                Detail
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="mt-6 rounded-xl border border-border bg-card p-4">
                    <p className="text-sm font-medium text-foreground">
                        Read-only
                    </p>

                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                        KDKMP tidak dapat mengubah Forecast
                        SPPG. Perubahan target kebutuhan,
                        periode, pembatalan, dan penutupan
                        tetap menjadi kewenangan SPPG.
                    </p>
                </div>
            </KdkmpLayout>
        </>
    );
}

function EmptyState() {
    return (
        <div className="flex min-h-64 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <ClipboardList className="size-5" />
            </div>

            <h2 className="mt-4 font-semibold text-foreground">
                Belum ada Forecast PUBLISHED
            </h2>

            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                Forecast akan muncul setelah SPPG yang
                terhubung menjadikan Forecast PUBLISHED
                dan KDKMP Anda merupakan PRIMARY aktif.
            </p>
        </div>
    );
}

function PublishedBadge() {
    return (
        <span className="inline-flex rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
            Dipublikasikan
        </span>
    );
}

function formatVolume(forecast) {
    const number = Number(forecast.target_volume);

    if (!Number.isFinite(number)) {
        return `${forecast.target_volume} ${forecast.unit.symbol}`;
    }

    return `${new Intl.NumberFormat("id-ID", {
        maximumFractionDigits:
            forecast.unit.decimal_precision ?? 2,
    }).format(number)} ${forecast.unit.symbol}`;
}

function formatDateTime(value) {
    if (!value) {
        return "—";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}