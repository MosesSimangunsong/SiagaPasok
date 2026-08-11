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

export default function Index({
    feedbacks = [],
}) {
    return (
        <KdkmpLayout>
            <Head title="Hasil Fulfilment" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <p className="text-sm font-medium text-blue-600">
                        Historical Result
                    </p>

                    <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">
                        Hasil Fulfilment
                    </h1>

                    <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Riwayat plan-vs-actual yang dicatat
                        SPPG untuk organisasi Anda. Data ini
                        bersifat read-only dan tidak mengubah
                        Commitment yang telah disetujui.
                    </p>
                </div>

                {feedbacks.length === 0 ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                        <h2 className="text-base font-semibold text-slate-900">
                            Belum ada hasil fulfilment
                        </h2>

                        <p className="mt-2 text-sm text-slate-500">
                            Hasil akan muncul setelah SPPG
                            mencatat realisasi untuk organisasi
                            Anda.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Forecast
                                        </th>

                                        <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Planned
                                        </th>

                                        <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Delivered
                                        </th>

                                        <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Result
                                        </th>

                                        <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Tanggal
                                        </th>

                                        <th className="px-5 py-3" />
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-100">
                                    {feedbacks.map(
                                        (feedback) => (
                                            <tr
                                                key={
                                                    feedback.id
                                                }
                                            >
                                                <td className="px-5 py-4">
                                                    <p className="text-sm font-semibold text-slate-950">
                                                        {
                                                            feedback
                                                                .forecast
                                                                .forecast_code
                                                        }
                                                    </p>

                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {
                                                            feedback
                                                                .forecast
                                                                .commodity
                                                                ?.name
                                                        }
                                                    </p>
                                                </td>

                                                <td className="whitespace-nowrap px-5 py-4 text-sm font-medium text-slate-700">
                                                    {
                                                        feedback.planned_volume_snapshot
                                                    }{" "}
                                                    {
                                                        feedback
                                                            .unit
                                                            ?.symbol
                                                    }
                                                </td>

                                                <td className="whitespace-nowrap px-5 py-4 text-sm font-medium text-slate-700">
                                                    {
                                                        feedback.delivered_volume
                                                    }{" "}
                                                    {
                                                        feedback
                                                            .unit
                                                            ?.symbol
                                                    }
                                                </td>

                                                <td className="px-5 py-4">
                                                    <span
                                                        className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${resultClasses(
                                                            feedback.result,
                                                        )}`}
                                                    >
                                                        {
                                                            feedback.result_label
                                                        }
                                                    </span>
                                                </td>

                                                <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                                    {formatDate(
                                                        feedback.fulfilment_date,
                                                    )}
                                                </td>

                                                <td className="px-5 py-4 text-right">
                                                    <Link
                                                        href={`/kdkmp/fulfilments/${feedback.id}`}
                                                        className="text-sm font-semibold text-blue-600 hover:text-blue-700"
                                                    >
                                                        Detail
                                                    </Link>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </KdkmpLayout>
    );
}