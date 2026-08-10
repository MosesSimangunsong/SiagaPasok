import KdkmpLayout from "@/Layouts/KdkmpLayout";
import { Head } from "@inertiajs/react";
import ExpectedHarvestForm from "./ExpectedHarvestForm";

export default function Create({
    producers,
    commodities,
    units,
    selectedProducerId,
}) {
    return (
        <>
            <Head
                title="Catat Expected Harvest — SiagaPasok"
            />

            <KdkmpLayout
                pageTitle="Catat Expected Harvest"
                pageDescription="Masukkan estimasi range dan window panen produsen."
            >
                <div className="max-w-4xl">
                    <ExpectedHarvestForm
                        producers={producers}
                        commodities={commodities}
                        units={units}
                        selectedProducerId={
                            selectedProducerId
                        }
                    />
                </div>
            </KdkmpLayout>
        </>
    );
}