import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import SppgLayout from "@/Layouts/SppgLayout";
import { Head, router } from "@inertiajs/react";
import {
    CalendarDays,
    ClipboardList,
    Plus,
} from "lucide-react";

export default function Index({ forecasts }) {
    return (
        <>
            <Head title="Forecast Kebutuhan — SiagaPasok" />

            <SppgLayout
                pageTitle="Forecast Kebutuhan"
                pageDescription="Kelola kebutuhan komoditas SPPG untuk periode mendatang."
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        onClick={() =>
                            router.visit(
                                "/sppg/forecasts/create",
                            )
                        }
                    >
                        <Plus data-icon="inline-start" />
                        Buat Forecast
                    </Button>
                }
            >
                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>
                            Daftar Forecast
                        </CardTitle>

                        <CardDescription>
                            Draft hanya terlihat oleh SPPG.
                            Forecast PUBLISHED akan tersedia
                            kepada PRIMARY KDKMP yang relevan.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                        {forecasts.length === 0 ? (
                            <div className="flex min-h-64 flex-col items-center justify-center px-6 text-center">
                                <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <ClipboardList className="size-5" />
                                </div>

                                <h2 className="mt-4 font-semibold text-foreground">
                                    Belum ada Forecast
                                </h2>

                                <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                                    Buat Draft Forecast pertama
                                    untuk mencatat kebutuhan
                                    komoditas SPPG.
                                </p>

                                <Button
                                    type="button"
                                    className="mt-5"
                                    onClick={() =>
                                        router.visit(
                                            "/sppg/forecasts/create",
                                        )
                                    }
                                >
                                    <Plus data-icon="inline-start" />
                                    Buat Forecast
                                </Button>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1040px] text-left text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Forecast
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
                                                            Version{" "}
                                                            {
                                                                forecast.version
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
                                                        <ForecastStatusBadge
                                                            forecast={
                                                                forecast
                                                            }
                                                        />
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <div className="flex justify-end gap-2">
                                                            {forecast
                                                                .can
                                                                ?.edit_draft && (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        router.visit(
                                                                            `/sppg/forecasts/${forecast.id}/edit`,
                                                                        )
                                                                    }
                                                                >
                                                                    Edit
                                                                </Button>
                                                            )}

                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    router.visit(
                                                                        `/sppg/forecasts/${forecast.id}`,
                                                                    )
                                                                }
                                                            >
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
            </SppgLayout>
        </>
    );
}

function ForecastStatusBadge({ forecast }) {
    const classes = {
        DRAFT:
            "bg-muted text-muted-foreground",
        PUBLISHED:
            "bg-primary/10 text-primary",
        CLOSED:
            "bg-muted text-foreground",
        CANCELLED:
            "bg-muted text-muted-foreground line-through",
    };

    return (
        <span
            className={[
                "inline-flex rounded-full px-2.5 py-1 text-xs font-medium",
                classes[forecast.status] ??
                    "bg-muted text-muted-foreground",
            ].join(" ")}
        >
            {forecast.status_label}
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