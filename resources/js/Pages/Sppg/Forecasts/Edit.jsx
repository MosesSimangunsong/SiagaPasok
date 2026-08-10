import SppgLayout from "@/Layouts/SppgLayout";
import { Head } from "@inertiajs/react";
import ForecastForm from "./ForecastForm";

export default function Edit({
    forecast,
    commodities,
    units,
}) {
    return (
        <>
            <Head
                title={`Edit ${forecast.forecast_code} — SiagaPasok`}
            />

            <SppgLayout
                pageTitle="Edit Draft Forecast"
                pageDescription={forecast.forecast_code}
            >
                <div className="max-w-4xl">
                    <ForecastForm
                        forecast={forecast}
                        commodities={commodities}
                        units={units}
                    />
                </div>
            </SppgLayout>
        </>
    );
}