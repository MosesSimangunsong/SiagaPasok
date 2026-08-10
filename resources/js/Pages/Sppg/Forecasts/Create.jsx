import SppgLayout from "@/Layouts/SppgLayout";
import { Head } from "@inertiajs/react";
import ForecastForm from "./ForecastForm";

export default function Create({
    commodities,
    units,
}) {
    return (
        <>
            <Head title="Buat Forecast — SiagaPasok" />

            <SppgLayout
                pageTitle="Buat Forecast"
                pageDescription="Catat kebutuhan komoditas sebagai Draft Forecast."
            >
                <div className="max-w-4xl">
                    <ForecastForm
                        commodities={commodities}
                        units={units}
                    />
                </div>
            </SppgLayout>
        </>
    );
}