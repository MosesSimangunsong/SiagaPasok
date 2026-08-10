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
    ArrowLeft,
    CalendarDays,
    LoaderCircle,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Power,
    PowerOff,
    UserRound,
} from "lucide-react";
import { useState } from "react";

export default function Show({
    producer,
    can,
}) {
    const [showStatePanel, setShowStatePanel] =
        useState(false);

    const activeStateForm = useForm({
        is_active: !producer.is_active,
    });

    const changeActiveState = () => {
        activeStateForm.patch(
            `/kdkmp/producers/${producer.id}/active-state`,
            {
                preserveScroll: true,
                onSuccess: () =>
                    setShowStatePanel(false),
            },
        );
    };

    return (
        <>
            <Head
                title={`${producer.name} — SiagaPasok`}
            />

            <KdkmpLayout
                pageTitle={producer.name}
                pageDescription={producer.producer_code}
                headerActions={
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.visit(
                                "/kdkmp/producers",
                            )
                        }
                    >
                        <ArrowLeft data-icon="inline-start" />
                        Daftar Produsen
                    </Button>
                }
            >
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <SummaryCard
                            label="Status"
                            value={
                                producer.is_active
                                    ? "Aktif"
                                    : "Nonaktif"
                            }
                        />

                        <SummaryCard
                            label="Kode Produsen"
                            value={
                                producer.producer_code
                            }
                        />

                        <SummaryCard
                            label="Expected Harvest"
                            value={`${producer.expected_harvest_count} catatan`}
                        />
                    </div>

                    <Card>
                        <CardHeader className="border-b">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>
                                        Informasi Produsen
                                    </CardTitle>

                                    <CardDescription>
                                        Identitas dan lokasi
                                        internal untuk koordinasi
                                        pasokan KDKMP.
                                    </CardDescription>
                                </div>

                                <ProducerStatusBadge
                                    active={
                                        producer.is_active
                                    }
                                />
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-7">
                            <div className="grid gap-5 md:grid-cols-2">
                                <DetailItem
                                    icon={UserRound}
                                    label="Nama Produsen"
                                    value={producer.name}
                                    description={
                                        producer.producer_code
                                    }
                                />

                                <DetailItem
                                    icon={Phone}
                                    label="Nomor Kontak"
                                    value={
                                        producer.contact_phone ||
                                        "Tidak tersedia"
                                    }
                                />
                            </div>

                            <div className="border-t border-border" />

                            <div className="grid gap-5 md:grid-cols-2">
                                <DetailItem
                                    icon={MapPin}
                                    label="Desa / Kelurahan"
                                    value={producer.village}
                                />

                                <DetailItem
                                    icon={MapPin}
                                    label="Kecamatan"
                                    value={producer.district}
                                />
                            </div>

                            <div className="border-t border-border" />

                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Catatan Internal
                                </p>

                                <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">
                                    {producer.notes ||
                                        "Tidak ada catatan."}
                                </p>
                            </div>

                            <div className="border-t border-border" />

                            <div className="grid gap-5 md:grid-cols-2">
                                <DetailItem
                                    label="Dibuat Oleh"
                                    value={
                                        producer.created_by
                                            ?.name ||
                                        "Tidak tersedia"
                                    }
                                />

                                <DetailItem
                                    label="Terakhir Diperbarui"
                                    value={formatDateTime(
                                        producer.updated_at,
                                    )}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>
                                        Expected Harvest
                                    </CardTitle>

                                    <CardDescription>
                                        Riwayat estimasi panen
                                        produsen. Data ini bukan
                                        Supply Commitment dan tidak
                                        masuk Safe Supply.
                                    </CardDescription>
                                </div>

                                {can.createExpectedHarvest && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() =>
                                            router.visit(
                                                `/kdkmp/expected-harvests/create?producer_id=${producer.id}`,
                                            )
                                        }
                                    >
                                        <Plus data-icon="inline-start" />
                                        Tambah Expected Harvest
                                    </Button>
                                )}
                            </div>
                        </CardHeader>

                        <CardContent className="p-0">
                            {producer
                                .expected_harvests
                                .length === 0 ? (
                                <div className="px-6 py-12 text-center">
                                    <CalendarDays className="mx-auto size-6 text-muted-foreground" />

                                    <p className="mt-3 font-medium text-foreground">
                                        Belum ada Expected Harvest
                                    </p>

                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Estimasi panen akan
                                        tampil di sini setelah
                                        dicatat Operator.
                                    </p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[900px] text-left text-sm">
                                        <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Komoditas
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Estimasi
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Window Panen
                                                </th>

                                                <th className="px-4 py-3 font-medium">
                                                    Terakhir Diperbarui
                                                </th>

                                                <th className="px-4 py-3 text-right font-medium">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {producer.expected_harvests.map(
                                                (
                                                    harvest,
                                                ) => (
                                                    <tr
                                                        key={
                                                            harvest.id
                                                        }
                                                    >
                                                        <td className="px-4 py-4">
                                                            <p className="font-medium text-foreground">
                                                                {
                                                                    harvest
                                                                        .commodity
                                                                        .name
                                                                }
                                                            </p>

                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {
                                                                    harvest
                                                                        .commodity
                                                                        .code
                                                                }
                                                            </p>
                                                        </td>

                                                        <td className="px-4 py-4 font-medium text-foreground">
                                                            {formatHarvestRange(
                                                                harvest,
                                                            )}
                                                        </td>

                                                        <td className="px-4 py-4 text-muted-foreground">
                                                            {formatDate(
                                                                harvest.harvest_start_at,
                                                            )}{" "}
                                                            –{" "}
                                                            {formatDate(
                                                                harvest.harvest_end_at,
                                                            )}
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <p className="text-muted-foreground">
                                                                {formatDateTime(
                                                                    harvest.updated_at,
                                                                )}
                                                            </p>

                                                            {harvest
                                                                .last_updated_by && (
                                                                <p className="mt-1 text-xs text-muted-foreground">
                                                                    {
                                                                        harvest
                                                                            .last_updated_by
                                                                            .name
                                                                    }
                                                                </p>
                                                            )}
                                                        </td>

                                                        <td className="px-4 py-4">
                                                            <div className="flex justify-end">
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        router.visit(
                                                                            `/kdkmp/expected-harvests/${harvest.id}`,
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

                    {(can.edit ||
                        can.setActiveState) && (
                        <Card>
                            <CardHeader className="border-b">
                                <CardTitle>
                                    Kelola Produsen
                                </CardTitle>

                                <CardDescription>
                                    Perubahan status tidak
                                    menghapus riwayat Expected
                                    Harvest.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {can.edit && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                router.visit(
                                                    `/kdkmp/producers/${producer.id}/edit`,
                                                )
                                            }
                                        >
                                            <Pencil data-icon="inline-start" />
                                            Edit Produsen
                                        </Button>
                                    )}

                                    {can.setActiveState && (
                                        <Button
                                            type="button"
                                            variant={
                                                producer.is_active
                                                    ? "destructive"
                                                    : "outline"
                                            }
                                            onClick={() =>
                                                setShowStatePanel(
                                                    !showStatePanel,
                                                )
                                            }
                                        >
                                            {producer.is_active ? (
                                                <PowerOff data-icon="inline-start" />
                                            ) : (
                                                <Power data-icon="inline-start" />
                                            )}

                                            {producer.is_active
                                                ? "Nonaktifkan"
                                                : "Aktifkan"}
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {showStatePanel && (
                        <Card>
                            <CardHeader className="border-b">
                                <CardTitle>
                                    {producer.is_active
                                        ? "Nonaktifkan produsen?"
                                        : "Aktifkan kembali produsen?"}
                                </CardTitle>

                                <CardDescription>
                                    {producer.is_active
                                        ? "Produsen tidak dapat dipakai untuk pencatatan pasokan baru selama berstatus nonaktif. Riwayat tetap disimpan."
                                        : "Produsen akan kembali tersedia untuk aktivitas operasional yang diizinkan."}
                                </CardDescription>
                            </CardHeader>

                            <CardFooter className="justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setShowStatePanel(
                                            false,
                                        )
                                    }
                                >
                                    Batal
                                </Button>

                                <Button
                                    type="button"
                                    variant={
                                        producer.is_active
                                            ? "destructive"
                                            : "default"
                                    }
                                    disabled={
                                        activeStateForm.processing
                                    }
                                    onClick={
                                        changeActiveState
                                    }
                                >
                                    {activeStateForm.processing && (
                                        <LoaderCircle
                                            data-icon="inline-start"
                                            className="animate-spin"
                                        />
                                    )}

                                    Konfirmasi
                                </Button>
                            </CardFooter>
                        </Card>
                    )}
                </div>
            </KdkmpLayout>
        </>
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
    icon: Icon,
    label,
    value,
    description,
}) {
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

                <p className="mt-1 font-medium text-foreground">
                    {value}
                </p>

                {description && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}

function ProducerStatusBadge({ active }) {
    return (
        <span
            className={[
                "inline-flex rounded-full px-2.5 py-1 text-xs font-medium",
                active
                    ? "bg-primary/10 text-primary"
                    : "bg-muted text-muted-foreground",
            ].join(" ")}
        >
            {active ? "Aktif" : "Nonaktif"}
        </span>
    );
}

function formatHarvestRange(harvest) {
    const min = Number(
        harvest.expected_min_volume,
    );

    const max = Number(
        harvest.expected_max_volume,
    );

    const precision =
        harvest.unit.decimal_precision ?? 2;

    const formatter = new Intl.NumberFormat(
        "id-ID",
        {
            maximumFractionDigits: precision,
        },
    );

    return `${formatter.format(min)}–${formatter.format(max)} ${harvest.unit.symbol}`;
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