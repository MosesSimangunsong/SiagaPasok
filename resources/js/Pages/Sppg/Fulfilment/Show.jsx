import { Head, Link, useForm } from "@inertiajs/react";
import SppgLayout from "@/Layouts/SppgLayout";

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

function FeedbackResult({
    feedback,
    unit,
}) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Hasil Pemenuhan
                    </p>

                    <span
                        className={`mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold ${resultClasses(
                            feedback.result,
                        )}`}
                    >
                        {feedback.result_label}
                    </span>
                </div>

                <div className="text-right">
                    <p className="text-xs text-slate-500">
                        Delivered Volume
                    </p>

                    <p className="text-lg font-bold text-slate-950">
                        {feedback.delivered_volume}{" "}
                        {unit?.symbol ?? ""}
                    </p>
                </div>
            </div>

            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-slate-500">
                        Tanggal Pemenuhan
                    </dt>
                    <dd className="mt-1 font-medium text-slate-900">
                        {formatDate(feedback.fulfilment_date)}
                    </dd>
                </div>

                <div>
                    <dt className="text-slate-500">
                        Dicatat
                    </dt>
                    <dd className="mt-1 font-medium text-slate-900">
                        {formatDate(feedback.recorded_at)}
                    </dd>
                </div>
            </dl>

            {feedback.reason_note && (
                <div className="mt-4 rounded-lg bg-white p-3">
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Alasan / Catatan
                    </p>

                    <p className="mt-1 text-sm leading-6 text-slate-700">
                        {feedback.reason_note}
                    </p>
                </div>
            )}
        </div>
    );
}

function ContributorFeedbackForm({
    forecast,
    contributor,
    unit,
}) {
    const defaultDate =
        forecast.required_end_at
            ? forecast.required_end_at.slice(0, 10)
            : "";

    const form = useForm({
        delivered_volume: "",
        fulfilment_date: defaultDate,
        reason_note: "",
    });

    const planned =
        Number(
            contributor.planned_volume_snapshot,
        ) || 0;

    const delivered =
        Number(
            form.data.delivered_volume,
        );

    const reasonLikelyRequired =
        form.data.delivered_volume !== ""
        && Number.isFinite(delivered)
        && delivered < planned;

    function submit(event) {
        event.preventDefault();

        form.post(
            `/sppg/forecasts/${forecast.id}/fulfilments/${contributor.organization.id}`,
            {
                preserveScroll: true,
            },
        );
    }

    return (
        <form
            onSubmit={submit}
            className="rounded-lg border border-blue-200 bg-blue-50/50 p-4"
        >
            <p className="text-sm font-semibold text-slate-900">
                Catat realisasi pemenuhan
            </p>

            <p className="mt-1 text-xs leading-5 text-slate-600">
                Result ditentukan otomatis oleh server dari
                delivered volume terhadap planned volume.
            </p>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label
                        htmlFor={`delivered-${contributor.organization.id}`}
                        className="text-sm font-medium text-slate-700"
                    >
                        Delivered Volume *
                    </label>

                    <div className="mt-1 flex rounded-lg border border-slate-300 bg-white focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                        <input
                            id={`delivered-${contributor.organization.id}`}
                            type="text"
                            inputMode="decimal"
                            value={
                                form.data
                                    .delivered_volume
                            }
                            onChange={(event) =>
                                form.setData(
                                    "delivered_volume",
                                    event.target.value,
                                )
                            }
                            className="min-w-0 flex-1 rounded-lg border-0 bg-transparent px-3 py-2 text-sm text-slate-950 outline-none"
                            placeholder="0.000000"
                        />

                        <span className="flex items-center px-3 text-sm text-slate-500">
                            {unit?.symbol ?? ""}
                        </span>
                    </div>

                    {form.errors.delivered_volume && (
                        <p className="mt-1 text-xs text-red-600">
                            {
                                form.errors
                                    .delivered_volume
                            }
                        </p>
                    )}
                </div>

                <div>
                    <label
                        htmlFor={`date-${contributor.organization.id}`}
                        className="text-sm font-medium text-slate-700"
                    >
                        Tanggal Pemenuhan *
                    </label>

                    <input
                        id={`date-${contributor.organization.id}`}
                        type="date"
                        value={
                            form.data
                                .fulfilment_date
                        }
                        onChange={(event) =>
                            form.setData(
                                "fulfilment_date",
                                event.target.value,
                            )
                        }
                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />

                    {form.errors.fulfilment_date && (
                        <p className="mt-1 text-xs text-red-600">
                            {
                                form.errors
                                    .fulfilment_date
                            }
                        </p>
                    )}
                </div>
            </div>

            <div className="mt-4">
                <label
                    htmlFor={`reason-${contributor.organization.id}`}
                    className="text-sm font-medium text-slate-700"
                >
                    Alasan / Catatan
                    {reasonLikelyRequired
                        ? " *"
                        : ""}
                </label>

                <textarea
                    id={`reason-${contributor.organization.id}`}
                    rows={3}
                    value={form.data.reason_note}
                    onChange={(event) =>
                        form.setData(
                            "reason_note",
                            event.target.value,
                        )
                    }
                    className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    placeholder={
                        reasonLikelyRequired
                            ? "Wajib dijelaskan karena realisasi di bawah planned volume."
                            : "Opsional jika pemenuhan terpenuhi."
                    }
                />

                {form.errors.reason_note && (
                    <p className="mt-1 text-xs text-red-600">
                        {form.errors.reason_note}
                    </p>
                )}
            </div>

            <div className="mt-4 flex justify-end">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {form.processing
                        ? "Menyimpan..."
                        : "Catat Fulfilment"}
                </button>
            </div>
        </form>
    );
}

function ContributorCard({
    forecast,
    contributor,
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Contributor
                    </p>

                    <h2 className="mt-1 text-lg font-semibold text-slate-950">
                        {contributor.organization.name}
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        {contributor.organization.code}
                    </p>
                </div>

                <div className="rounded-lg bg-slate-950 px-4 py-3 text-right text-white">
                    <p className="text-xs text-slate-300">
                        Planned Volume Snapshot
                    </p>

                    <p className="mt-1 text-lg font-bold">
                        {
                            contributor.planned_volume_snapshot
                        }{" "}
                        {forecast.unit?.symbol ?? ""}
                    </p>
                </div>
            </div>

            <div className="mt-5">
                {contributor.feedback ? (
                    <FeedbackResult
                        feedback={
                            contributor.feedback
                        }
                        unit={forecast.unit}
                    />
                ) : contributor.can_record ? (
                    <ContributorFeedbackForm
                        forecast={forecast}
                        contributor={
                            contributor
                        }
                        unit={forecast.unit}
                    />
                ) : (
                    <p className="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
                        Fulfilment tidak dapat dicatat.
                    </p>
                )}
            </div>
        </div>
    );
}

export default function Show({
    forecast,
    handoff,
    contributors = [],
    summary = {},
}) {
    return (
        <SppgLayout>
            <Head
                title={`Fulfilment ${forecast.forecast_code}`}
            />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <Link
                        href="/sppg/fulfilments"
                        className="text-sm font-medium text-blue-600 hover:text-blue-700"
                    >
                        ← Umpan Balik Pemenuhan
                    </Link>

                    <div className="mt-3 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p className="text-sm font-medium text-blue-600">
                                Forecast CLOSED
                            </p>

                            <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">
                                {forecast.forecast_code}
                            </h1>

                            <p className="mt-1 text-sm text-slate-600">
                                {forecast.commodity?.name}
                            </p>
                        </div>

                        <div className="grid grid-cols-3 gap-2 text-center">
                            <div className="rounded-lg bg-slate-100 px-4 py-3">
                                <p className="text-lg font-bold text-slate-950">
                                    {
                                        summary.contributor_count
                                    }
                                </p>
                                <p className="text-xs text-slate-500">
                                    Contributor
                                </p>
                            </div>

                            <div className="rounded-lg bg-emerald-50 px-4 py-3">
                                <p className="text-lg font-bold text-emerald-700">
                                    {summary.recorded_count}
                                </p>
                                <p className="text-xs text-emerald-700">
                                    Tercatat
                                </p>
                            </div>

                            <div className="rounded-lg bg-amber-50 px-4 py-3">
                                <p className="text-lg font-bold text-amber-700">
                                    {summary.pending_count}
                                </p>
                                <p className="text-xs text-amber-700">
                                    Belum
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p className="text-sm font-semibold text-blue-950">
                        Historical RFP Handoff
                    </p>

                    <p className="mt-1 text-sm leading-6 text-blue-800">
                        Planned volume di bawah berasal dari
                        historical Ready for Procurement
                        handoff #{handoff.observation_id}.
                        Nilai ini immutable untuk pencatatan
                        fulfilment.
                    </p>
                </div>

                {contributors.length === 0 ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                        <p className="text-sm text-slate-500">
                            Tidak ada contributor pada
                            historical handoff ini.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {contributors.map(
                            (contributor) => (
                                <ContributorCard
                                    key={
                                        contributor
                                            .organization
                                            .id
                                    }
                                    forecast={forecast}
                                    contributor={
                                        contributor
                                    }
                                />
                            ),
                        )}
                    </div>
                )}
            </div>
        </SppgLayout>
    );
} 