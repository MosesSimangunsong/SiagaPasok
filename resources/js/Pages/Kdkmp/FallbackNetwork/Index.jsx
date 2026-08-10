export default function Index({
    requests = [],
}) {
    return (
        <div>
            <h1>Fallback Network</h1>

            <p>
                {requests.length} request tersedia.
            </p>
        </div>
    );
}