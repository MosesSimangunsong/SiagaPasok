export default function Create({
    forecasts = [],
    selectedForecastId = null,
}) {
    return (
        <div>
            <h1>Create Fallback Request</h1>

            <p>
                {forecasts.length} forecast tersedia.
            </p>

            {selectedForecastId && (
                <p>
                    Selected Forecast: {selectedForecastId}
                </p>
            )}
        </div>
    );
}