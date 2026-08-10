import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import KdkmpLayout from "@/Layouts/KdkmpLayout";
import {
    Head,
    router,
    useForm,
} from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    CircleX,
    LoaderCircle,
} from "lucide-react";
import { useState } from "react";

const textareaClassName =
    "w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-primary/15";

function FieldError({ message }) {
    if (!message) {
        return null;
    }

    return (
        <p className="mt-1.5 text-sm text-destructive">
            {message}
        </p>
    );
}

export default function Show({
    review,
    can,
}) {
    const [activeAction, setActiveAction] =
        useState(null);

    const approveForm = useForm({});

    const rejectForm = useForm({
        review_reason: "",
    });

    const {
        commitment,
        version,
        active_version: activeVersion,
        is_revision: isRevision,
    } = review;

    const exceedsExpectedHarvest =
        commitment.expected_harvest &&
        Number(version.max_volume) >
            Number(
                commitment.expected_harvest
                    .expected_max_volume,
            );

    const approve = () => {
        approveForm.post(
            `/kdkmp/manager/approvals/${commitment.id}/versions/${version.id}/approve`,
            {
                preserveScroll: true,
            },
        );
    };

    const reject = (event) => {
        event.preventDefault();

        rejectForm.post(
            `/kdkmp/manager/approvals/${commitment.id}/versions/${version.id}/reject`,
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head
                title={`Review Commitment #${commitment.id} — SiagaPasok`}
            />

            <KdkmpLayout
                pageTitle={`Review Commitment #${commitment.id}`}
                pageDescription={`${commitment.forecast.forecast_code} • Version ${version.version_no}`}
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                "/kdkmp/manager/approvals",
                            )
                        }
                    >
                        <ArrowLeft data-icon="inline-start" />
                        Approval Queue
                    </Button>
                }
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Tipe Review"
                            value={
                                isRevision
                                    ? "Revisi Commitment"
                                    : "Persetujuan Awal"
                            }
                        />

                        <SummaryCard
                            label="Approval State"
                            value="Menunggu Persetujuan"
                        />

                        <SummaryCard
                            label="Confidence Saat Ini"
                            value={
                                commitment.current_confidence_label ??
                                "Belum ada"
                            }
                        />
                    </div>

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Konteks Forecast
                            </CardTitle>

                            <CardDescription>
                                Kebutuhan SPPG yang menjadi
                                dasar Commitment.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="grid gap-5 md:grid-cols-2">
                            <DetailItem
                                label="Forecast"
                                value={
                                    commitment.forecast
                                        .forecast_code
                                }
                                description={`${commitment.forecast.sppg.name} • ${commitment.forecast.sppg.code}`}
                            />

                            <DetailItem
                                label="Komoditas"
                                value={
                                    commitment.forecast
                                        .commodity.name
                                }
                                description={
                                    commitment.forecast
                                        .commodity.code
                                }
                            />

                            <DetailItem
                                label="Demand Target"
                                value={`${formatNumber(
                                    commitment.forecast
                                        .target_volume,
                                )} ${
                                    commitment.forecast
                                        .unit.symbol
                                }`}
                            />

                            <DetailItem
                                label="Periode Dibutuhkan"
                                value={formatDateTime(
                                    commitment.forecast
                                        .required_start_at,
                                )}
                                description={`s.d. ${formatDateTime(
                                    commitment.forecast
                                        .required_end_at,
                                )}`}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Sumber Pasokan
                            </CardTitle>

                            <CardDescription>
                                Konteks Producer dan
                                Expected Harvest tetap
                                read-only bagi Manager.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <div className="grid gap-5 md:grid-cols-2">
                                <DetailItem
                                    label="Produsen"
                                    value={
                                        commitment.producer
                                            .name
                                    }
                                    description={`${commitment.producer.producer_code} • ${commitment.producer.village}, Kec. ${commitment.producer.district}`}
                                />

                                <DetailItem
                                    label="Status Produsen"
                                    value={
                                        commitment.producer
                                            .is_active
                                            ? "Aktif"
                                            : "Nonaktif"
                                    }
                                />
                            </div>

                            {commitment.expected_harvest ? (
                                <>
                                    <div className="border-t border-border" />

                                    <div className="grid gap-5 md:grid-cols-2">
                                        <DetailItem
                                            label="Ekspektasi Panen"
                                            value={`${formatNumber(
                                                commitment
                                                    .expected_harvest
                                                    .expected_min_volume,
                                            )}–${formatNumber(
                                                commitment
                                                    .expected_harvest
                                                    .expected_max_volume,
                                            )} ${
                                                commitment
                                                    .expected_harvest
                                                    .unit
                                                    .symbol
                                            }`}
                                        />

                                        <DetailItem
                                            label="Window Panen"
                                            value={formatDate(
                                                commitment
                                                    .expected_harvest
                                                    .harvest_start_at,
                                            )}
                                            description={`s.d. ${formatDate(
                                                commitment
                                                    .expected_harvest
                                                    .harvest_end_at,
                                            )}`}
                                        />
                                    </div>
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Commitment tidak
                                    dihubungkan ke Expected
                                    Harvest tertentu.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {isRevision &&
                        activeVersion && (
                            <VersionCard
                                title={`Active Approved Version ${activeVersion.version_no}`}
                                description="Payload approved yang saat ini masih berlaku."
                                version={
                                    activeVersion
                                }
                                muted
                            />
                        )}

                    <VersionCard
                        title={
                            isRevision
                                ? `Proposed Revision — Version ${version.version_no}`
                                : `Proposed Commitment — Version ${version.version_no}`
                        }
                        description="Payload berikut adalah data yang sedang menunggu keputusan Manager."
                        version={version}
                    />

                    {exceedsExpectedHarvest && (
                        <Card className="border-amber-500/30">
                            <CardContent>
                                <div className="flex items-start gap-3">
                                    <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-400" />

                                    <div>
                                        <p className="font-medium text-foreground">
                                            Commitment
                                            melebihi
                                            Expected Harvest
                                        </p>

                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            Kondisi ini
                                            merupakan soft
                                            warning. Evaluasi
                                            justification
                                            Operator sebelum
                                            mengambil
                                            keputusan.
                                        </p>

                                        <p className="mt-3 whitespace-pre-wrap text-sm font-medium text-foreground">
                                            {version.operator_justification ||
                                                "Operator belum memberikan justification."}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {(can.approve ||
                        can.reject) && (
                        <Card>
                            <CardHeader className="border-b">
                                <CardTitle>
                                    Keputusan Manager
                                </CardTitle>

                                <CardDescription>
                                    Manager tidak dapat
                                    memperbaiki payload.
                                    Jika data tidak layak,
                                    Reject dengan alasan dan
                                    Operator membuat revisi.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {can.approve && (
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "approve"
                                                        ? null
                                                        : "approve",
                                                )
                                            }
                                        >
                                            <CheckCircle2 data-icon="inline-start" />
                                            Approve
                                        </Button>
                                    )}

                                    {can.reject && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "reject"
                                                        ? null
                                                        : "reject",
                                                )
                                            }
                                        >
                                            <CircleX data-icon="inline-start" />
                                            Reject
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {activeAction ===
                        "approve" && (
                        <ApprovePanel
                            form={approveForm}
                            isRevision={
                                isRevision
                            }
                            onConfirm={approve}
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}

                    {activeAction ===
                        "reject" && (
                        <RejectPanel
                            form={rejectForm}
                            onSubmit={reject}
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}
                </div>
            </KdkmpLayout>
        </>
    );
}

function ApprovePanel({
    form,
    isRevision,
    onConfirm,
    onCancel,
}) {
    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Approve Commitment?
                </CardTitle>

                <CardDescription>
                    {isRevision
                        ? "Version baru akan menjadi active version. Confidence tidak otomatis kembali GREEN."
                        : "Approval pertama akan menetapkan active version dan memulai Confidence GREEN."}
                </CardDescription>
            </CardHeader>

            <CardContent>
                <FieldError
                    message={
                        form.errors
                            .approval_status
                    }
                />
            </CardContent>

            <CardFooter className="justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                >
                    Batal
                </Button>

                <Button
                    type="button"
                    disabled={form.processing}
                    onClick={onConfirm}
                >
                    {form.processing && (
                        <LoaderCircle
                            data-icon="inline-start"
                            className="animate-spin"
                        />
                    )}

                    <CheckCircle2 data-icon="inline-start" />
                    Approve Commitment
                </Button>
            </CardFooter>
        </Card>
    );
}

function RejectPanel({
    form,
    onSubmit,
    onCancel,
}) {
    return (
        <form onSubmit={onSubmit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        Reject Commitment
                    </CardTitle>

                    <CardDescription>
                        Alasan wajib diberikan agar
                        Operator dapat memperbaiki
                        Commitment melalui version baru.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <label
                        htmlFor="review_reason"
                        className="mb-2 block text-sm font-medium text-foreground"
                    >
                        Alasan Penolakan
                    </label>

                    <textarea
                        id="review_reason"
                        rows={5}
                        value={
                            form.data
                                .review_reason
                        }
                        onChange={(event) =>
                            form.setData(
                                "review_reason",
                                event.target.value,
                            )
                        }
                        className={
                            textareaClassName
                        }
                        placeholder="Jelaskan alasan Commitment belum dapat disetujui."
                        required
                    />

                    <FieldError
                        message={
                            form.errors
                                .review_reason
                        }
                    />
                </CardContent>

                <CardFooter className="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                    >
                        Batal
                    </Button>

                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={form.processing}
                    >
                        {form.processing && (
                            <LoaderCircle
                                data-icon="inline-start"
                                className="animate-spin"
                            />
                        )}

                        <CircleX data-icon="inline-start" />
                        Reject Commitment
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

function VersionCard({
    title,
    description,
    version,
    muted = false,
}) {
    return (
        <Card
            className={
                muted
                    ? "bg-muted/15"
                    : undefined
            }
        >
            <CardHeader className="border-b">
                <CardTitle>{title}</CardTitle>

                <CardDescription>
                    {description}
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-6">
                <div className="grid gap-5 md:grid-cols-2">
                    <DetailItem
                        label="Range Pasokan"
                        value={`${formatNumber(
                            version.min_volume,
                        )}–${formatNumber(
                            version.max_volume,
                        )} ${
                            version.unit.symbol
                        }`}
                    />

                    <DetailItem
                        label="Window Ketersediaan"
                        value={formatDateTime(
                            version.availability_start_at,
                        )}
                        description={`s.d. ${formatDateTime(
                            version.availability_end_at,
                        )}`}
                    />
                </div>

                <TextSection
                    label="Catatan"
                    value={version.notes}
                />

                {version.change_reason && (
                    <TextSection
                        label="Alasan Revisi"
                        value={
                            version.change_reason
                        }
                    />
                )}

                {version.operator_justification && (
                    <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                        <TextSection
                            label="Justification Operator"
                            value={
                                version.operator_justification
                            }
                        />
                    </div>
                )}

                <div className="grid gap-5 border-t border-border pt-5 md:grid-cols-3">
                    <DetailItem
                        label="Maker"
                        value={
                            version.created_by
                                ?.name ?? "—"
                        }
                    />

                    <DetailItem
                        label="Submitter"
                        value={
                            version.submitted_by
                                ?.name ?? "—"
                        }
                    />

                    <DetailItem
                        label="Submitted"
                        value={formatDateTime(
                            version.submitted_at,
                        )}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function TextSection({ label, value }) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>

            <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                {value || "Tidak ada."}
            </p>
        </div>
    );
}

function SummaryCard({ label, value }) {
    return (
        <Card>
            <CardContent>
                <p className="text-sm text-muted-foreground">
                    {label}
                </p>

                <p className="mt-2 text-lg font-semibold text-foreground">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

function DetailItem({
    label,
    value,
    description,
}) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>

            <p className="mt-1 font-medium text-foreground">
                {value}
            </p>

            {description && (
                <p className="mt-0.5 text-xs text-muted-foreground">
                    {description}
                </p>
            )}
        </div>
    );
}

function formatNumber(value) {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return value ?? "—";
    }

    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 6,
    }).format(number);
}

function formatDate(value) {
    if (!value) {
        return "—";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
    }).format(new Date(value));
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