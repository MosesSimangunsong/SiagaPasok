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
    Eye,
    Plus,
    Users,
} from "lucide-react";

export default function Index({
    producers,
    canCreate,
}) {
    return (
        <>
            <Head title="Produsen — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Produsen"
                pageDescription="Kelola registry produsen internal KDKMP dan konteks produksi lokal."
                headerActions={
                    canCreate ? (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() =>
                                router.visit(
                                    "/kdkmp/producers/create",
                                )
                            }
                        >
                            <Plus data-icon="inline-start" />
                            Tambah Produsen
                        </Button>
                    ) : null
                }
            >
                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>
                            Registry Produsen
                        </CardTitle>

                        <CardDescription>
                            Data produsen hanya tersedia
                            untuk organisasi KDKMP Anda.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                        {producers.length === 0 ? (
                            <EmptyState
                                canCreate={canCreate}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1180px] text-left text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Nama Produsen
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Lokasi / Desa
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Komoditas Terkait
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Ekspektasi Panen Terdekat
                                            </th>

                                            <th className="px-4 py-3 font-medium">
                                                Terakhir Diperbarui
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
                                        {producers.map(
                                            (producer) => (
                                                <tr
                                                    key={
                                                        producer.id
                                                    }
                                                >
                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                producer.name
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                producer.producer_code
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                producer.village
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            Kec.{" "}
                                                            {
                                                                producer.district
                                                            }
                                                        </p>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <CommodityList
                                                            commodities={
                                                                producer.planning_commodities
                                                            }
                                                        />
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <NearestHarvest
                                                            harvest={
                                                                producer.nearest_expected_harvest
                                                            }
                                                        />
                                                    </td>

                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {formatDateTime(
                                                            producer.updated_at,
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <ProducerStatusBadge
                                                            producer={
                                                                producer
                                                            }
                                                        />
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <div className="flex justify-end">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    router.visit(
                                                                        `/kdkmp/producers/${producer.id}`,
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
            </KdkmpLayout>
        </>
    );
}

function EmptyState({ canCreate }) {
    return (
        <div className="flex min-h-64 flex-col items-center justify-center px-6 text-center">
            <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Users className="size-5" />
            </div>

            <h2 className="mt-4 font-semibold text-foreground">
                Belum ada produsen
            </h2>

            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                Tambahkan produsen lokal untuk mulai
                mencatat Expected Harvest secara
                terstruktur.
            </p>

            {canCreate && (
                <Button
                    type="button"
                    className="mt-5"
                    onClick={() =>
                        router.visit(
                            "/kdkmp/producers/create",
                        )
                    }
                >
                    <Plus data-icon="inline-start" />
                    Tambah Produsen
                </Button>
            )}
        </div>
    );
}

function CommodityList({ commodities = [] }) {
    if (commodities.length === 0) {
        return (
            <span className="text-muted-foreground">
                —
            </span>
        );
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {commodities.map((commodity) => (
                <span
                    key={commodity.id}
                    className="inline-flex rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                >
                    {commodity.name}
                </span>
            ))}
        </div>
    );
}

function NearestHarvest({ harvest }) {
    if (!harvest) {
        return (
            <span className="text-muted-foreground">
                Belum ada
            </span>
        );
    }

    return (
        <div className="flex gap-2">
            <CalendarDays className="mt-0.5 size-4 shrink-0 text-muted-foreground" />

            <div>
                <p className="font-medium text-foreground">
                    {harvest.commodity_name}
                </p>

                <p className="mt-1 text-xs text-muted-foreground">
                    {formatVolumeRange(harvest)}
                </p>

                <p className="mt-1 text-xs text-muted-foreground">
                    {formatDate(
                        harvest.harvest_start_at,
                    )}{" "}
                    –{" "}
                    {formatDate(
                        harvest.harvest_end_at,
                    )}
                </p>
            </div>
        </div>
    );
}

function ProducerStatusBadge({ producer }) {
    return (
        <span
            className={[
                "inline-flex rounded-full px-2.5 py-1 text-xs font-medium",
                producer.is_active
                    ? "bg-primary/10 text-primary"
                    : "bg-muted text-muted-foreground",
            ].join(" ")}
        >
            {producer.is_active
                ? "Aktif"
                : "Nonaktif"}
        </span>
    );
}

function formatVolumeRange(harvest) {
    const min = Number(
        harvest.expected_min_volume,
    );

    const max = Number(
        harvest.expected_max_volume,
    );

    const precision =
        harvest.unit?.decimal_precision ?? 2;

    const format = (value) =>
        Number.isFinite(value)
            ? new Intl.NumberFormat("id-ID", {
                  maximumFractionDigits:
                      precision,
              }).format(value)
            : value;

    return `${format(min)}–${format(max)} ${
        harvest.unit?.symbol ?? ""
    }`;
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