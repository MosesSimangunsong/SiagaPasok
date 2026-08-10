export default function Show({
    request,
    can = {},
}) {
    return (
        <div>
            <h1>Fallback Broadcast</h1>

            <p>
                Request #{request?.id}
            </p>

            <p>
                {request?.remaining_volume}{" "}
                {request?.unit?.symbol} masih dibutuhkan.
            </p>

            {can.createOffer && (
                <p>
                    Operator dapat membuat penawaran.
                </p>
            )}
        </div>
    );
}