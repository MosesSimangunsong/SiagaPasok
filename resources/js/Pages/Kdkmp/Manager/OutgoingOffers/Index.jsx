export default function Index({
    offers = [],
}) {
    return (
        <div>
            <h1>Outgoing Offer Review</h1>
            <p>{offers.length} offer menunggu review.</p>
        </div>
    );
}