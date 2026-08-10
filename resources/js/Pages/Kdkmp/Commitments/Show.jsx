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
    Activity,
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    CircleHelp,
    CircleX,
    Clock3,
    FileClock,
    LoaderCircle,
    Pencil,
    RefreshCcw,
    Send,
    ShieldAlert,
    TriangleAlert,
} from "lucide-react";
import { useState } from "react";

const inputClassName =
    "h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary focus:ring-3 focus:ring-primary/15";

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
    commitment,
    can,
}) {
    const [activeAction, setActiveAction] =
        useState(null);

    const submitForm = useForm({});

    const downgradeForm = useForm({
        to_confidence:
            commitment.current_confidence ===
            "YELLOW"
                ? "RED"
                : "YELLOW",

        reason_code: "",
        reason_note: "",
    });

    const recoveryForm = useForm({
        recovery_reason: "",
    });

    const latestVersion =
        commitment.latest_version;

    const activeVersion =
        commitment.active_version;

    const hasDifferentLatestVersion =
        activeVersion &&
        latestVersion &&
        activeVersion.id !==
            latestVersion.id;

    const submitVersion = () => {
        if (!latestVersion) {
            return;
        }

        submitForm.post(
            `/kdkmp/commitments/${commitment.id}/versions/${latestVersion.id}/submit`,
            {
                preserveScroll: true,
                onSuccess: () =>
                    setActiveAction(null),
            },
        );
    };

    const downgrade = (event) => {
        event.preventDefault();

        downgradeForm.post(
            `/kdkmp/commitments/${commitment.id}/confidence/downgrade`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setActiveAction(null);
                    downgradeForm.reset();
                },
            },
        );
    };

    const requestRecovery = (event) => {
        event.preventDefault();

        recoveryForm.post(
            `/kdkmp/commitments/${commitment.id}/recovery-requests`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setActiveAction(null);
                    recoveryForm.reset();
                },
            },
        );
    };

    return (
        <>
            <Head
                title={`Komitmen #${commitment.id} — SiagaPasok`}
            />

            <KdkmpLayout
                pageTitle={`Komitmen Pasokan #${commitment.id}`}
                pageDescription={`${commitment.forecast.forecast_code} • ${commitment.commodity.name} • ${commitment.producer.name}`}
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                "/kdkmp/commitments",
                            )
                        }
                    >
                        <ArrowLeft data-icon="inline-start" />
                        Daftar Commitment
                    </Button>
                }
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Workflow"
                            value={
                                commitment
                                    .workflow_state
                                    ?.label ?? "—"
                            }
                        />

                        <SummaryCard
                            label="Confidence"
                            value={
                                commitment
                                    .current_confidence_label ??
                                "Belum ada"
                            }
                        />

                        <SummaryCard
                            label="Terakhir Diverifikasi"
                            value={
                                commitment.last_confidence_verified_at
                                    ? formatDateTime(
                                          commitment.last_confidence_verified_at,
                                      )
                                    : "Belum diverifikasi"
                            }
                        />
                    </div>

                    {commitment.current_confidence ===
                        "RED" && (
                        <Card className="border-red-500/30">
                            <CardContent>
                                <div className="flex items-start gap-3">
                                    <CircleX className="mt-0.5 size-5 shrink-0 text-red-600" />

                                    <div>
                                        <p className="font-medium text-foreground">
                                            Commitment
                                            berstatus Kritis
                                        </p>

                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            Status Kritis
                                            bersifat terminal
                                            untuk commitment
                                            ini. Jika pasokan
                                            baru tersedia,
                                            buat commitment
                                            baru.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Konteks Commitment
                            </CardTitle>

                            <CardDescription>
                                Identitas logical
                                commitment dan Forecast
                                yang menjadi acuannya.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <div className="grid gap-5 md:grid-cols-2">
                                <DetailItem
                                    label="Forecast"
                                    value={
                                        commitment
                                            .forecast
                                            .forecast_code
                                    }
                                    description={
                                        commitment
                                            .forecast
                                            .sppg_name
                                    }
                                />

                                <DetailItem
                                    label="Komoditas"
                                    value={
                                        commitment
                                            .commodity
                                            .name
                                    }
                                />

                                <DetailItem
                                    label="Produsen"
                                    value={
                                        commitment
                                            .producer
                                            .name
                                    }
                                    description={
                                        commitment
                                            .producer
                                            .producer_code
                                    }
                                />

                                <DetailItem
                                    label="Lifecycle"
                                    value={
                                        commitment
                                            .lifecycle_label
                                    }
                                />
                            </div>

                            <div className="grid gap-5 md:grid-cols-2">
                                <DetailItem
                                    label="Periode Forecast"
                                    value={formatDateTime(
                                        commitment
                                            .forecast
                                            .required_start_at,
                                    )}
                                    description={`s.d. ${formatDateTime(
                                        commitment
                                            .forecast
                                            .required_end_at,
                                    )}`}
                                />

                                <DetailItem
                                    label="Dibuat"
                                    value={formatDateTime(
                                        commitment
                                            .created_at,
                                    )}
                                />
                            </div>

                            {commitment.expected_harvest && (
                                <>
                                    <div className="border-t border-border" />

                                    <section>
                                        <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            Ekspektasi
                                            Panen Terkait
                                        </p>

                                        <div className="grid gap-5 md:grid-cols-2">
                                            <DetailItem
                                                label="Range Ekspektasi"
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
                                                        ?.symbol ??
                                                    ""
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
                                    </section>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {activeVersion && (
                        <PayloadCard
                            title={`Approved Active Payload — Version ${activeVersion.version_no}`}
                            description="Payload approved yang saat ini aktif. Data ini tidak diedit in-place."
                            version={
                                activeVersion
                            }
                            highlighted
                        />
                    )}

                    {!activeVersion &&
                        latestVersion && (
                            <PayloadCard
                                title={`Current Draft Payload — Version ${latestVersion.version_no}`}
                                description="Belum ada approved active version untuk commitment ini."
                                version={
                                    latestVersion
                                }
                            />
                        )}

                    {hasDifferentLatestVersion && (
                        <PayloadCard
                            title={`Version Terbaru — Version ${latestVersion.version_no}`}
                            description="Revision ini belum menggantikan approved active payload sampai Manager menyetujuinya."
                            version={
                                latestVersion
                            }
                        />
                    )}

                    {(can.editLatestDraft ||
                        can.submitLatestDraft ||
                        can.createRevision ||
                        can.downgradeConfidence ||
                        can.requestRecovery) && (
                        <Card>
                            <CardHeader className="border-b">
                                <CardTitle>
                                    Aksi Operator
                                </CardTitle>

                                <CardDescription>
                                    State transition
                                    dilakukan melalui
                                    command eksplisit dan
                                    diverifikasi ulang oleh
                                    backend.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {can.editLatestDraft &&
                                        latestVersion && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    router.visit(
                                                        `/kdkmp/commitments/${commitment.id}/versions/${latestVersion.id}/edit`,
                                                    )
                                                }
                                            >
                                                <Pencil data-icon="inline-start" />
                                                Edit Draft
                                            </Button>
                                        )}

                                    {can.submitLatestDraft && (
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "submit"
                                                        ? null
                                                        : "submit",
                                                )
                                            }
                                        >
                                            <Send data-icon="inline-start" />
                                            Ajukan
                                            Persetujuan
                                        </Button>
                                    )}

                                    {can.createRevision && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                router.visit(
                                                    `/kdkmp/commitments/${commitment.id}/revisions/create`,
                                                )
                                            }
                                        >
                                            <FileClock data-icon="inline-start" />
                                            Revisi
                                            Commitment
                                        </Button>
                                    )}

                                    {can.downgradeConfidence && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "risk"
                                                        ? null
                                                        : "risk",
                                                )
                                            }
                                        >
                                            <ShieldAlert data-icon="inline-start" />
                                            Laporkan Risiko
                                        </Button>
                                    )}

                                    {can.requestRecovery && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setActiveAction(
                                                    activeAction ===
                                                        "recovery"
                                                        ? null
                                                        : "recovery",
                                                )
                                            }
                                        >
                                            <RefreshCcw data-icon="inline-start" />
                                            Ajukan
                                            Pemulihan
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {activeAction === "submit" && (
                        <SubmitPanel
                            form={submitForm}
                            onConfirm={
                                submitVersion
                            }
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}

                    {activeAction === "risk" && (
                        <RiskPanel
                            form={downgradeForm}
                            currentConfidence={
                                commitment.current_confidence
                            }
                            onSubmit={downgrade}
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}

                    {activeAction ===
                        "recovery" && (
                        <RecoveryPanel
                            form={recoveryForm}
                            onSubmit={
                                requestRecovery
                            }
                            onCancel={() =>
                                setActiveAction(null)
                            }
                        />
                    )}

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Confidence Condition
                            </CardTitle>

                            <CardDescription>
                                Confidence menggambarkan
                                kondisi commitment saat
                                ini. APPROVED tidak berarti
                                GREEN secara permanen.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <div className="flex flex-wrap items-center gap-3">
                                <ConfidenceBadge
                                    value={
                                        commitment.current_confidence
                                    }
                                    label={
                                        commitment.current_confidence_label
                                    }
                                />

                                <span className="text-sm text-muted-foreground">
                                    Terakhir
                                    diverifikasi{" "}
                                    {commitment.last_confidence_verified_at
                                        ? formatDateTime(
                                              commitment.last_confidence_verified_at,
                                          )
                                        : "—"}
                                </span>
                            </div>

                            {commitment
                                .confidence_events
                                .length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada riwayat
                                    confidence.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {commitment.confidence_events.map(
                                        (
                                            event,
                                        ) => (
                                            <ConfidenceEvent
                                                key={
                                                    event.id
                                                }
                                                event={
                                                    event
                                                }
                                            />
                                        ),
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Approval & Version
                                History
                            </CardTitle>

                            <CardDescription>
                                Setiap approved payload
                                dipertahankan sebagai
                                version historis.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-3">
                            {commitment.versions.map(
                                (version) => (
                                    <VersionHistoryItem
                                        key={
                                            version.id
                                        }
                                        version={
                                            version
                                        }
                                        active={
                                            commitment
                                                .active_version
                                                ?.id ===
                                            version.id
                                        }
                                    />
                                ),
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>
                                Recovery History
                            </CardTitle>

                            <CardDescription>
                                YELLOW hanya kembali
                                menjadi GREEN melalui
                                Recovery yang disetujui
                                Manager.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            {commitment
                                .recovery_requests
                                .length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada Recovery
                                    Request.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {commitment.recovery_requests.map(
                                        (
                                            recovery,
                                        ) => (
                                            <RecoveryHistoryItem
                                                key={
                                                    recovery.id
                                                }
                                                recovery={
                                                    recovery
                                                }
                                            />
                                        ),
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </KdkmpLayout>
        </>
    );
}

function SubmitPanel({
    form,
    onConfirm,
    onCancel,
}) {
    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>
                    Ajukan Commitment?
                </CardTitle>

                <CardDescription>
                    Setelah dikirim, payload version
                    dikunci dan menunggu keputusan
                    Manager.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <FieldError
                    message={
                        form.errors
                            .approval_status
                    }
                />

                <FieldError
                    message={
                        form.errors
                            .operator_justification
                    }
                />

                <FieldError
                    message={
                        form.errors
                            .change_reason
                    }
                />
            </CardContent>

            <CardFooter className="justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                >
                    Kembali
                </Button>

                <Button
                    type="button"
                    disabled={
                        form.processing
                    }
                    onClick={onConfirm}
                >
                    {form.processing && (
                        <LoaderCircle
                            data-icon="inline-start"
                            className="animate-spin"
                        />
                    )}

                    <Send data-icon="inline-start" />
                    Ajukan Persetujuan
                </Button>
            </CardFooter>
        </Card>
    );
}

function RiskPanel({
    form,
    currentConfidence,
    onSubmit,
    onCancel,
}) {
    const targets =
        currentConfidence === "GREEN"
            ? [
                  {
                      value: "YELLOW",
                      label: "Berisiko",
                  },
                  {
                      value: "RED",
                      label: "Kritis",
                  },
              ]
            : [
                  {
                      value: "RED",
                      label: "Kritis",
                  },
              ];

    const reasons = [
        "Cuaca",
        "Hama",
        "Volume turun",
        "Jadwal bergeser",
        "Logistik",
        "Lainnya",
    ];

    return (
        <form onSubmit={onSubmit}>
            <Card className="border-amber-500/30">
                <CardHeader className="border-b">
                    <CardTitle>
                        Laporkan Risiko Pasokan
                    </CardTitle>

                    <CardDescription>
                        Downgrade berlaku langsung
                        setelah disimpan dan tidak
                        membutuhkan Manager approval.
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-5">
                    <div className="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                        <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-400" />

                        <p className="text-sm leading-6 text-foreground">
                            Perubahan confidence dapat
                            langsung mengurangi Pasokan
                            Aman dan memunculkan
                            kekurangan pasokan.
                        </p>
                    </div>

                    <div>
                        <label
                            htmlFor="to_confidence"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Status Baru
                        </label>

                        <select
                            id="to_confidence"
                            value={
                                form.data
                                    .to_confidence
                            }
                            onChange={(event) =>
                                form.setData(
                                    "to_confidence",
                                    event.target
                                        .value,
                                )
                            }
                            className={
                                inputClassName
                            }
                            required
                        >
                            {targets.map(
                                (target) => (
                                    <option
                                        key={
                                            target.value
                                        }
                                        value={
                                            target.value
                                        }
                                    >
                                        {
                                            target.label
                                        }
                                    </option>
                                ),
                            )}
                        </select>

                        <FieldError
                            message={
                                form.errors
                                    .to_confidence
                            }
                        />
                    </div>

                    <div>
                        <label
                            htmlFor="reason_code"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Alasan
                        </label>

                        <select
                            id="reason_code"
                            value={
                                form.data
                                    .reason_code
                            }
                            onChange={(event) =>
                                form.setData(
                                    "reason_code",
                                    event.target
                                        .value,
                                )
                            }
                            className={
                                inputClassName
                            }
                            required
                        >
                            <option value="">
                                Pilih alasan
                            </option>

                            {reasons.map(
                                (reason) => (
                                    <option
                                        key={
                                            reason
                                        }
                                        value={
                                            reason
                                        }
                                    >
                                        {reason}
                                    </option>
                                ),
                            )}
                        </select>

                        <FieldError
                            message={
                                form.errors
                                    .reason_code
                            }
                        />
                    </div>

                    <div>
                        <label
                            htmlFor="reason_note"
                            className="mb-2 block text-sm font-medium text-foreground"
                        >
                            Catatan Kondisi
                        </label>

                        <textarea
                            id="reason_note"
                            rows={4}
                            value={
                                form.data
                                    .reason_note
                            }
                            onChange={(event) =>
                                form.setData(
                                    "reason_note",
                                    event.target
                                        .value,
                                )
                            }
                            className={
                                textareaClassName
                            }
                            placeholder="Jelaskan kondisi aktual yang menyebabkan perubahan confidence."
                            required
                        />

                        <FieldError
                            message={
                                form.errors
                                    .reason_note
                            }
                        />
                    </div>
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
                        disabled={
                            form.processing
                        }
                    >
                        {form.processing && (
                            <LoaderCircle
                                data-icon="inline-start"
                                className="animate-spin"
                            />
                        )}

                        <ShieldAlert data-icon="inline-start" />
                        Simpan Perubahan Risiko
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

function RecoveryPanel({
    form,
    onSubmit,
    onCancel,
}) {
    return (
        <form onSubmit={onSubmit}>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>
                        Ajukan Pemulihan ke Aman
                    </CardTitle>

                    <CardDescription>
                        Commitment tetap YELLOW sampai
                        Recovery ini disetujui Manager.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <label
                        htmlFor="recovery_reason"
                        className="mb-2 block text-sm font-medium text-foreground"
                    >
                        Alasan / Evidence Operasional
                    </label>

                    <textarea
                        id="recovery_reason"
                        rows={5}
                        value={
                            form.data
                                .recovery_reason
                        }
                        onChange={(event) =>
                            form.setData(
                                "recovery_reason",
                                event.target.value,
                            )
                        }
                        className={
                            textareaClassName
                        }
                        placeholder="Jelaskan kondisi terbaru yang mendukung pemulihan confidence."
                        required
                    />

                    <FieldError
                        message={
                            form.errors
                                .recovery_reason
                        }
                    />

                    <FieldError
                        message={
                            form.errors
                                .current_confidence
                        }
                    />

                    <FieldError
                        message={
                            form.errors.recovery
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
                        disabled={
                            form.processing
                        }
                    >
                        {form.processing && (
                            <LoaderCircle
                                data-icon="inline-start"
                                className="animate-spin"
                            />
                        )}

                        <RefreshCcw data-icon="inline-start" />
                        Ajukan Pemulihan
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

function PayloadCard({
    title,
    description,
    version,
    highlighted = false,
}) {
    return (
        <Card
            className={
                highlighted
                    ? "border-primary/25"
                    : undefined
            }
        >
            <CardHeader className="border-b">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <CardTitle>
                            {title}
                        </CardTitle>

                        <CardDescription>
                            {description}
                        </CardDescription>
                    </div>

                    <ApprovalBadge
                        value={
                            version.approval_status
                        }
                        label={
                            version.approval_status_label
                        }
                    />
                </div>
            </CardHeader>

            <CardContent className="space-y-6">
                <div className="grid gap-5 md:grid-cols-2">
                    <DetailItem
                        label="Range Pasokan"
                        value={formatRange(
                            version,
                        )}
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

                <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Catatan
                    </p>

                    <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                        {version.notes ||
                            "Tidak ada catatan."}
                    </p>
                </div>

                {version.change_reason && (
                    <div>
                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Alasan Revisi
                        </p>

                        <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                            {
                                version.change_reason
                            }
                        </p>
                    </div>
                )}

                {version.operator_justification && (
                    <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                        <p className="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">
                            Justification Operator
                        </p>

                        <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                            {
                                version.operator_justification
                            }
                        </p>
                    </div>
                )}

                {version.review_reason && (
                    <div className="rounded-xl border border-border bg-muted/30 p-4">
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Catatan Review
                        </p>

                        <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                            {
                                version.review_reason
                            }
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ConfidenceEvent({ event }) {
    return (
        <div className="flex gap-3 rounded-xl border border-border p-4">
            <Activity className="mt-0.5 size-4 shrink-0 text-muted-foreground" />

            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <ConfidenceBadge
                        value={
                            event.to_confidence
                        }
                    />

                    <span className="text-xs text-muted-foreground">
                        {event.source ===
                        "SYSTEM"
                            ? "SYSTEM"
                            : event.actor
                              ? event.actor
                                    .name
                              : "User"}
                    </span>
                </div>

                <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                    {event.reason_note ||
                        "Tidak ada catatan."}
                </p>

                {event.reason_code && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        Alasan:{" "}
                        {event.reason_code}
                    </p>
                )}

                <p className="mt-2 text-xs text-muted-foreground">
                    {formatDateTime(
                        event.occurred_at,
                    )}
                </p>
            </div>
        </div>
    );
}

function VersionHistoryItem({
    version,
    active,
}) {
    return (
        <div className="rounded-xl border border-border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-medium text-foreground">
                        Version{" "}
                        {version.version_no}
                        {active
                            ? " • Active"
                            : ""}
                    </p>

                    <p className="mt-1 text-sm text-muted-foreground">
                        {formatRange(version)}
                    </p>
                </div>

                <ApprovalBadge
                    value={
                        version.approval_status
                    }
                    label={
                        version.approval_status_label
                    }
                />
            </div>

            <div className="mt-4 grid gap-3 text-sm md:grid-cols-3">
                <HistoryActor
                    label="Maker"
                    actor={
                        version.created_by
                    }
                />

                <HistoryActor
                    label="Submitter"
                    actor={
                        version.submitted_by
                    }
                />

                <HistoryActor
                    label="Reviewer"
                    actor={
                        version.reviewed_by
                    }
                />
            </div>

            <p className="mt-3 text-xs text-muted-foreground">
                Dibuat{" "}
                {formatDateTime(
                    version.created_at,
                )}
                {version.submitted_at
                    ? ` • Diajukan ${formatDateTime(
                          version.submitted_at,
                      )}`
                    : ""}
                {version.reviewed_at
                    ? ` • Direview ${formatDateTime(
                          version.reviewed_at,
                      )}`
                    : ""}
            </p>

            {version.review_reason && (
                <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-muted-foreground">
                    {version.review_reason}
                </p>
            )}
        </div>
    );
}

function RecoveryHistoryItem({
    recovery,
}) {
    return (
        <div className="rounded-xl border border-border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-medium text-foreground">
                        Recovery Version{" "}
                        {
                            recovery.commitment_version_id
                        }
                    </p>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Diajukan oleh{" "}
                        {
                            recovery.requested_by
                                .name
                        }
                    </p>
                </div>

                <ApprovalBadge
                    value={
                        recovery.status
                    }
                    label={
                        recovery.status_label
                    }
                />
            </div>

            <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-foreground">
                {
                    recovery.recovery_reason
                }
            </p>

            <p className="mt-3 text-xs text-muted-foreground">
                {formatDateTime(
                    recovery.requested_at,
                )}
            </p>

            {recovery.review_reason && (
                <div className="mt-3 rounded-lg bg-muted/40 p-3">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Catatan Review
                    </p>

                    <p className="mt-1 text-sm text-foreground">
                        {
                            recovery.review_reason
                        }
                    </p>
                </div>
            )}
        </div>
    );
}

function HistoryActor({
    label,
    actor,
}) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </p>

            <p className="mt-1 font-medium text-foreground">
                {actor?.name ?? "—"}
            </p>
        </div>
    );
}

function SummaryCard({
    label,
    value,
}) {
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

function ApprovalBadge({
    value,
    label,
}) {
    const pending =
        value === "DRAFT" ||
        value === "PENDING_APPROVAL";

    const rejected =
        value === "REJECTED";

    const approved =
        value === "APPROVED";

    const Icon = approved
        ? CheckCircle2
        : rejected
          ? CircleX
          : pending
            ? Clock3
            : CircleHelp;

    return (
        <span
            className={[
                "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
                approved
                    ? "bg-primary/10 text-primary"
                    : "bg-muted text-muted-foreground",
            ].join(" ")}
        >
            <Icon className="size-3.5" />
            {label ?? value ?? "—"}
        </span>
    );
}

function ConfidenceBadge({
    value,
    label,
}) {
    const config = {
        GREEN: {
            icon: CheckCircle2,
            className:
                "bg-green-500/10 text-green-700 dark:text-green-400",
            label: "Aman",
        },

        YELLOW: {
            icon: TriangleAlert,
            className:
                "bg-amber-500/10 text-amber-700 dark:text-amber-400",
            label: "Berisiko",
        },

        RED: {
            icon: CircleX,
            className:
                "bg-red-500/10 text-red-700 dark:text-red-400",
            label: "Kritis",
        },
    };

    const selected =
        config[value];

    if (!selected) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                <CircleHelp className="size-3.5" />
                Belum ada
            </span>
        );
    }

    const Icon = selected.icon;

    return (
        <span
            className={[
                "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
                selected.className,
            ].join(" ")}
        >
            <Icon className="size-3.5" />
            {label ?? selected.label}
        </span>
    );
}

function formatRange(version) {
    return `${formatNumber(
        version.min_volume,
        version.unit?.decimal_precision,
    )}–${formatNumber(
        version.max_volume,
        version.unit?.decimal_precision,
    )} ${version.unit?.symbol ?? ""}`;
}

function formatNumber(
    value,
    precision = 6,
) {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return value ?? "—";
    }

    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits:
            precision ?? 6,
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