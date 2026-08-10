export default function AdminStatusBadge({ active }) {
    return (
        <span
            className={[
                "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
                active
                    ? "bg-primary/10 text-primary"
                    : "bg-muted text-muted-foreground",
            ].join(" ")}
        >
            <span
                className={[
                    "size-1.5 rounded-full",
                    active ? "bg-primary" : "bg-muted-foreground/60",
                ].join(" ")}
            />

            {active ? "Aktif" : "Nonaktif"}
        </span>
    );
}