export default function Index({
    requests = [],
    canCreate = false,
}) {
    return (
        <div>
            <h1>Fallback Requests</h1>

            <p>
                {requests.length} fallback request
            </p>

            {canCreate && (
                <p>
                    Operator dapat membuat Fallback Request baru.
                </p>
            )}
        </div>
    );
}