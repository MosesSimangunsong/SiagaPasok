import { Head, Link } from "@inertiajs/react";
import KdkmpLayout from "@/Layouts/KdkmpLayout";

function formatDate(value) {
    if (!value) {
        return "—";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
    }).format(new Date(value));
}

function resultClasses(result) {
    if (result === "FULFILLED") {
        return "bg-emerald-100 text-emerald-700";
    }

    if (result === "PARTIAL") {
        return "bg-amber-100 text-amber-700";
    }

    return "bg-red-100 text-red-700";
}

export default function Show({
    feedback,
}) {
    return (
        <KdkmpLayout>
            <Head
                title={`Fulfilment ${feedback.forecast.forecast_code}`}
            />

            <div className="mx-auto w-full max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <Link
                        href="/kdkmp/fulfilments"
                        className="text-sm font-medium text-blue-600 hover:text-blue-700"
                    >
                        ← Hasil Fulfilment
                    </Link>

                    <div className="mt-3">
                        <p className="text-sm font-medium text-blue-600">
                            Historical Result
                        </p>

                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">
                            {
                                feedback.forecast
                                    .forecast_code
                            }
                        </h1>

                        <p className="mt-1 text-sm text-slate-600">
                            {
                                feedback.forecast
                                    .commodity?.name
                            }
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Result
                            </p>

                            <span
                                className={`mt-2 inline-flex rounded-full px-3 py-1 text-sm font-semibold ${resultClasses(
                                    feedback.result,
                                )}`}
                            >
                                {feedback.result_label}
                            </span>
                        </div>

                        <div className="text-left sm:text-right">
                            <p className="text-xs text-slate-500">
                                Dicatat oleh SPPG
                            </p>

                            <p className="mt-1 text-sm font-semibold text-slate-900">
                                {
                                    feedback.forecast
                                        .sppg?.name
                                }
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                        <div className="rounded-lg bg-slate-50 p-4">
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Planned Volume Snapshot
                            </p>

                            <p className="mt-2 text-xl font-bold text-slate-950">
                                {
                                    feedback.planned_volume_snapshot
                                }{" "}
                                {feedback.unit?.symbol}
                            </p>
                        </div>

                        <div className="rounded-lg bg-slate-50 p-4">
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Delivered Volume
                            </p>

                            <p className="mt-2 text-xl font-bold text-slate-950">
                                {
                                    feedback.delivered_volume
                                }{" "}
                                {feedback.unit?.symbol}
                            </p>
                        </div>
                    </div>

                    <dl className="mt-6 grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
                        <div>
                            <dt className="text-sm text-slate-500">
                                Tanggal Pemenuhan
                            </dt>

                            <dd className="mt-1 text-sm font-semibold text-slate-900">
                                {formatDate(
                                    feedback.fulfilment_date,
                                )}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-sm text-slate-500">
                                Dicatat Pada
                            </dt>

                            <dd className="mt-1 text-sm font-semibold text-slate-900">
                                {formatDate(
                                    feedback.recorded_at,
                                )}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-sm text-slate-500">
                                Periode Forecast
                            </dt>

                            <dd className="mt-1 text-sm font-semibold text-slate-900">
                                {formatDate(
                                    feedback.forecast
                                        .required_start_at,
                                )}{" "}
                                –{" "}
                                {formatDate(
                                    feedback.forecast
                                        .required_end_at,
                                )}
                            </dd>
                        </div>
                    </dl>

                    {feedback.reason_note && (
                        <div className="mt-6 border-t border-slate-100 pt-5">
                            <p className="text-sm font-medium text-slate-700">
                                Alasan / Catatan
                            </p>

                            <p className="mt-2 rounded-lg bg-slate-50 p-4 text-sm leading-6 text-slate-700">
                                {feedback.reason_note}
                            </p>
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p className="text-sm font-semibold text-blue-950">
                        Historical Feedback — Read Only
                    </p>

                    <p className="mt-1 text-sm leading-6 text-blue-800">
                        Hasil ini tidak mengubah approved
                        Commitment, confidence, Safe Supply,
                        atau histori pasokan organisasi Anda.
                    </p>
                </div>
            </div>
        </KdkmpLayout>
    );
}