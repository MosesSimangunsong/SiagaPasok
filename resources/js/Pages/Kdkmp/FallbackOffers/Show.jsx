export default function Show({
    offer,
    can = {},
}) {
    return (
        <div>
            <h1>Fallback Offer</h1>

            <p>Offer #{offer?.id}</p>
            <p>Status: {offer?.status_label}</p>

            {can.submit && <p>Offer dapat disubmit.</p>}
            {can.approve && <p>Offer dapat disetujui.</p>}
            {can.reject && <p>Offer dapat ditolak.</p>}
            {can.withdraw && <p>Offer dapat ditarik.</p>}
        </div>
    );
}