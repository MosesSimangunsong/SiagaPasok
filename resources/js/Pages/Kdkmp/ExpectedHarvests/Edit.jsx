import KdkmpLayout from "@/Layouts/KdkmpLayout";
import { Head } from "@inertiajs/react";
import ExpectedHarvestForm from "./ExpectedHarvestForm";

export default function Edit({
    expectedHarvest,
    producers,
    commodities,
    units,
}) {
    return (
        <>
            <Head
                title={`Edit Expected Harvest — ${expectedHarvest.producer.name}`}
            />

            <KdkmpLayout
                pageTitle="Edit Expected Harvest"
                pageDescription={`${expectedHarvest.producer.name} • ${expectedHarvest.commodity.name}`}
            >
                <div className="max-w-4xl">
                    <ExpectedHarvestForm
                        expectedHarvest={
                            expectedHarvest
                        }
                        producers={producers}
                        commodities={commodities}
                        units={units}
                    />
                </div>
            </KdkmpLayout>
        </>
    );
}