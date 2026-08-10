import KdkmpLayout from "@/Layouts/KdkmpLayout";
import { Head } from "@inertiajs/react";
import CommitmentForm from "./CommitmentForm";

export default function Create({
    forecasts,
    producers,
    expectedHarvests,
    selectedForecastId,
}) {
    return (
        <>
            <Head title="Buat Komitmen Pasokan — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Buat Komitmen Pasokan"
                pageDescription="Siapkan range pasokan untuk Forecast PUBLISHED sebelum diajukan kepada Manager."
            >
                <div className="max-w-5xl">
                    <CommitmentForm
                        forecasts={forecasts}
                        producers={producers}
                        expectedHarvests={
                            expectedHarvests
                        }
                        selectedForecastId={
                            selectedForecastId
                        }
                    />
                </div>
            </KdkmpLayout>
        </>
    );
}