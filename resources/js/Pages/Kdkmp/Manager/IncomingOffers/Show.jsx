export default function Show({
    offer,
    can = {},
}) {
    return (
        <div>
            <h1>Incoming Fallback Offer</h1>

            <p>Offer #{offer?.id}</p>
            <p>Status: {offer?.status_label}</p>

            {can.accept && <p>Offer dapat diterima.</p>}
            {can.reject && <p>Offer dapat ditolak.</p>}
        </div>
    );
}