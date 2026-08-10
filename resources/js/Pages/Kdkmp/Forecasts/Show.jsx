import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    Clock3,
    Handshake,
    MapPin,
    Send,
} from "lucide-react";
import KdkmpLayout from "@/Layouts/KdkmpLayout";
import { Head, router } from "@inertiajs/react";
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    Clock3,
    MapPin,
    Send,
} from "lucide-react";

export default function Show({ forecast, canCreateCommitment }) {
    return (
        <>
            <Head title={`${forecast.forecast_code} — SiagaPasok`} />

            <KdkmpLayout
                pageTitle={forecast.forecast_code}
                pageDescription={`${forecast.sppg.name} • ${forecast.commodity.name}`}
                headerActions={
                    <>
                        {canCreateCommitment && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() =>
                                    router.visit(
                                        `/kdkmp/commitments/create?forecast_id=${forecast.id}`,
                                    )
                                }
                            >
                                <Handshake data-icon="inline-start" />
                                Buat Commitment
                            </Button>
                        )}

                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => router.visit("/kdkmp/forecasts")}
                        >
                            <ArrowLeft data-icon="inline-start" />
                            Daftar Forecast
                        </Button>
                    </>
                }
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Status"
                            value={forecast.status_label}
                        />

                        <SummaryCard
                            label="Target Volume"
                            value={formatVolume(forecast)}
                        />

                        <SummaryCard
                            label="Freshness Interval"
                            value={
                                forecast.freshness_interval_hours
                                    ? `${forecast.freshness_interval_hours} jam`
                                    : "Tidak ditentukan"
                            }
                        />
                    </div>

                    <Card>
                        <CardHeader className="border-b">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>Detail Kebutuhan SPPG</CardTitle>

                                    <CardDescription>
                                        Informasi Forecast PUBLISHED untuk
                                        perencanaan pasokan KDKMP.
                                    </CardDescription>
                                </div>

                                <span className="inline-flex rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                                    {forecast.status_label}
                                </span>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-7">
                            <section>
                                <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    SPPG
                                </p>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <DetailItem
                                        icon={Building2}
                                        label="Organisasi"
                                        value={forecast.sppg.name}
                                        description={forecast.sppg.code}
                                    />

                                    <DetailItem
                                        icon={MapPin}
                                        label="Lokasi"
                                        value={
                                            forecast.sppg.general_location ||
                                            "Tidak tersedia"
                                        }
                                    />
                                </div>
                            </section>

                            <div className="border-t border-border" />

                            <section>
                                <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Kebutuhan
                                </p>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <DetailItem
                                        label="Komoditas"
                                        value={forecast.commodity.name}
                                        description={forecast.commodity.code}
                                    />

                                    <DetailItem
                                        label="Target Volume"
                                        value={formatVolume(forecast)}
                                    />
                                </div>
                            </section>

                            <div className="border-t border-border" />

                            <section>
                                <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Periode Kebutuhan
                                </p>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <DetailItem
                                        icon={CalendarDays}
                                        label="Mulai Dibutuhkan"
                                        value={formatDateTime(
                                            forecast.required_start_at,
                                        )}
                                    />

                                    <DetailItem
                                        icon={CalendarDays}
                                        label="Batas Akhir"
                                        value={formatDateTime(
                                            forecast.required_end_at,
                                        )}
                                    />
                                </div>
                            </section>

                            <div className="border-t border-border" />

                            <section>
                                <div className="grid gap-5 md:grid-cols-2">
                                    <DetailItem
                                        icon={Clock3}
                                        label="Freshness Interval"
                                        value={
                                            forecast.freshness_interval_hours
                                                ? `${forecast.freshness_interval_hours} jam`
                                                : "Tidak ditentukan"
                                        }
                                    />

                                    <DetailItem
                                        icon={Send}
                                        label="Dipublikasikan"
                                        value={formatDateTime(
                                            forecast.published_at,
                                        )}
                                    />
                                </div>
                            </section>

                            <div className="border-t border-border" />

                            <section>
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Catatan SPPG
                                </p>

                                <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                                    {forecast.notes || "Tidak ada catatan."}
                                </p>
                            </section>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent>
                            <div>
                                <p className="font-medium text-foreground">
                                    Forecast read-only
                                </p>

                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                    Halaman ini tidak menyediakan Edit, Revisi,
                                    Publish, Close, atau Cancel. Lifecycle
                                    Forecast sepenuhnya dikendalikan oleh SPPG.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </KdkmpLayout>
        </>
    );
}

function SummaryCard({ label, value }) {
    return (
        <Card>
            <CardContent>
                <p className="text-sm text-muted-foreground">{label}</p>

                <p className="mt-2 text-lg font-semibold text-foreground">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

function DetailItem({ icon: Icon, label, value, description }) {
    return (
        <div className="flex items-start gap-3">
            {Icon && (
                <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-4" />
                </div>
            )}

            <div>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    {label}
                </p>

                <p className="mt-1 font-medium text-foreground">{value}</p>

                {description && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}

function formatVolume(forecast) {
    const number = Number(forecast.target_volume);

    if (!Number.isFinite(number)) {
        return `${forecast.target_volume} ${forecast.unit.symbol}`;
    }

    return `${new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: forecast.unit.decimal_precision ?? 2,
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
