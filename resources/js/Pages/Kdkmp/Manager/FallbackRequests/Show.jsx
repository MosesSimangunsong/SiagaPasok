export default function Show({
    review,
    can = {},
}) {
    return (
        <div>
            <h1>Fallback Request Review</h1>

            <p>
                Request #{review?.id}
            </p>

            <p>
                Status: {review?.status_label}
            </p>

            {can.approve && (
                <p>
                    Request dapat disetujui.
                </p>
            )}

            {can.reject && (
                <p>
                    Request dapat ditolak.
                </p>
            )}
        </div>
    );
}