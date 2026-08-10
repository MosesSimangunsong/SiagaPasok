export default function Show({
    fallbackRequest,
    can = {},
}) {
    return (
        <div>
            <h1>Fallback Request</h1>

            <p>
                Request #{fallbackRequest?.id}
            </p>

            <p>
                Status: {fallbackRequest?.status_label}
            </p>

            {can.submit && (
                <p>
                    Request dapat disubmit.
                </p>
            )}

            {can.cancel && (
                <p>
                    Request dapat dibatalkan.
                </p>
            )}
        </div>
    );
}