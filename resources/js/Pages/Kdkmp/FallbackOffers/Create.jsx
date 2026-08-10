export default function Create({
    request,
    sourceCommitments = [],
}) {
    return (
        <div>
            <h1>Create Fallback Offer</h1>

            <p>
                Request #{request?.id}
            </p>

            <p>
                {sourceCommitments.length} eligible source commitment.
            </p>
        </div>
    );
} 