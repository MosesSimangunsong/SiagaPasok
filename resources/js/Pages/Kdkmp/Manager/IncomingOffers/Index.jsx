export default function Index({
    offers = [],
}) {
    return (
        <div>
            <h1>Incoming Offers</h1>
            <p>{offers.length} offer menunggu keputusan.</p>
        </div>
    );
}