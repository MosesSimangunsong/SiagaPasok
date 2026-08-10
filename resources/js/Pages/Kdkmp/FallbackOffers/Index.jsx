export default function Index({ offers = [] }) {
    return (
        <div>
            <h1>Fallback Offers</h1>
            <p>{offers.length} offer.</p>
        </div>
    );
}