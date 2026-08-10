export default function Index({
    requests = [],
}) {
    return (
        <div>
            <h1>Fallback Request Approval</h1>

            <p>
                {requests.length} request menunggu review.
            </p>
        </div>
    );
}