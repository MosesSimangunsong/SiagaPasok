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
} from "lucide-react";

export default function Index({
    expectedHarvests,
    canCreate,
}) {
    return (
        <>
            <Head
                title="Expected Harvest — SiagaPasok"
            />

            <KdkmpLayout
                pageTitle="Expected Harvest"
                pageDescription="Kelola estimasi kapasitas panen internal KDKMP."
                headerActions={
                    canCreate ? (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() =>
                                router.visit(
                                    "/kdkmp/expected-harvests/create",
                                )
                            }
                        >
                            <Plus data-icon="inline-start" />
                            Catat Expected Harvest
                        </Button>
                    ) : null
                }
            >
                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>
                            Daftar Expected Harvest
                        </CardTitle>

                        <CardDescription>
                            Estimasi ini menjadi planning
                            context internal dan tidak dihitung
                            sebagai Safe Supply.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                        {expectedHarvests.length === 0 ? (
                            <EmptyState
                                canCreate={canCreate}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1080px] text-left text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Produsen
                                            </th>

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
                                        {expectedHarvests.map(
                                            (harvest) => (
                                                <tr
                                                    key={
                                                        harvest.id
                                                    }
                                                >
                                                    <td className="px-4 py-4">
                                                        <p className="font-medium text-foreground">
                                                            {
                                                                harvest
                                                                    .producer
                                                                    .name
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                harvest
                                                                    .producer
                                                                    .producer_code
                                                            }
                                                        </p>
                                                    </td>

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
                                                        {formatRange(
                                                            harvest,
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <div className="flex gap-2 text-muted-foreground">
                                                            <CalendarDays className="mt-0.5 size-4 shrink-0" />

                                                            <div>
                                                                <p>
                                                                    {formatDate(
                                                                        harvest.harvest_start_at,
                                                                    )}
                                                                </p>

                                                                <p className="mt-1 text-xs">
                                                                    s.d.{" "}
                                                                    {formatDate(
                                                                        harvest.harvest_end_at,
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-4">
                                                        <p className="text-muted-foreground">
                                                            {formatDateTime(
                                                                harvest.updated_at,
                                                            )}
                                                        </p>

                                                        {harvest.last_updated_by && (
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
                <CalendarDays className="size-5" />
            </div>

            <h2 className="mt-4 font-semibold text-foreground">
                Belum ada Expected Harvest
            </h2>

            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                Catat estimasi panen produsen untuk
                membangun konteks perencanaan pasokan.
            </p>

            {canCreate && (
                <Button
                    type="button"
                    className="mt-5"
                    onClick={() =>
                        router.visit(
                            "/kdkmp/expected-harvests/create",
                        )
                    }
                >
                    <Plus data-icon="inline-start" />
                    Catat Expected Harvest
                </Button>
            )}
        </div>
    );
}

function formatRange(harvest) {
    const precision =
        harvest.unit.decimal_precision ?? 2;

    const formatter = new Intl.NumberFormat(
        "id-ID",
        {
            maximumFractionDigits: precision,
        },
    );

    return `${formatter.format(
        Number(harvest.expected_min_volume),
    )}–${formatter.format(
        Number(harvest.expected_max_volume),
    )} ${harvest.unit.symbol}`;
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