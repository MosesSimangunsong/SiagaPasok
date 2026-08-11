import { Head, Link } from "@inertiajs/react";
import SppgLayout from "@/Layouts/SppgLayout";

function formatDate(value) {
    if (!value) {
        return "—";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
    }).format(new Date(value));
}

function ProgressBadge({ recorded, total }) {
    if (total > 0 && recorded >= total) {
        return (
            <span className="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                Selesai
            </span>
        );
    }

    return (
        <span className="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
            Perlu Dicatat
        </span>
    );
}

export default function Index({
    forecasts = [],
}) {
    return (
        <SppgLayout>
            <Head title="Umpan Balik Pemenuhan" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <p className="text-sm font-medium text-blue-600">
                        Historical Plan vs Actual
                    </p>

                    <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">
                        Umpan Balik Pemenuhan
                    </h1>

                    <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Catat realisasi pemenuhan setelah proses resmi di luar
                        SiagaPasok selesai. Planned volume berasal dari
                        historical Ready for Procurement handoff dan tidak
                        dihitung ulang dari kondisi pasokan saat ini.
                    </p>
                </div>

                {forecasts.length === 0 ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                        <h2 className="text-base font-semibold text-slate-900">
                            Belum ada Forecast yang dapat dicatat
                        </h2>

                        <p className="mt-2 text-sm text-slate-500">
                            Forecast harus berstatus CLOSED dan memiliki
                            historical Ready for Procurement handoff.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {forecasts.map((forecast) => (
                            <div
                                key={forecast.id}
                                className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="text-lg font-semibold text-slate-950">
                                                {forecast.forecast_code}
                                            </h2>

                                            <ProgressBadge
                                                recorded={
                                                    forecast.feedback_recorded_count
                                                }
                                                total={
                                                    forecast.contributor_count
                                                }
                                            />
                                        </div>

                                        <p className="mt-1 text-sm font-medium text-slate-700">
                                            {forecast.commodity?.name ?? "—"}
                                        </p>

                                        <p className="mt-2 text-sm text-slate-500">
                                            Periode kebutuhan{" "}
                                            {formatDate(
                                                forecast.required_start_at,
                                            )}{" "}
                                            –{" "}
                                            {formatDate(
                                                forecast.required_end_at,
                                            )}
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-3 gap-3 text-center">
                                        <div className="rounded-lg bg-slate-50 px-4 py-3">
                                            <p className="text-lg font-bold text-slate-950">
                                                {forecast.contributor_count}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                Contributor
                                            </p>
                                        </div>

                                        <div className="rounded-lg bg-emerald-50 px-4 py-3">
                                            <p className="text-lg font-bold text-emerald-700">
                                                {
                                                    forecast.feedback_recorded_count
                                                }
                                            </p>
                                            <p className="text-xs text-emerald-700">
                                                Tercatat
                                            </p>
                                        </div>

                                        <div className="rounded-lg bg-amber-50 px-4 py-3">
                                            <p className="text-lg font-bold text-amber-700">
                                                {
                                                    forecast.feedback_pending_count
                                                }
                                            </p>
                                            <p className="text-xs text-amber-700">
                                                Belum
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                                    <p className="text-xs text-slate-500">
                                        Forecast ditutup{" "}
                                        {formatDate(forecast.closed_at)}
                                    </p>

                                    <Link
                                        href={`/sppg/forecasts/${forecast.id}/fulfilments`}
                                        className="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                    >
                                        Buka Fulfilment
                                    </Link>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </SppgLayout>
    );
}