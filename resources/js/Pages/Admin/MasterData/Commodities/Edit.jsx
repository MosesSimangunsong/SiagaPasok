import AdminLayout from "@/Layouts/AdminLayout";
import CommodityForm from "./CommodityForm";
import { Head } from "@inertiajs/react";

export default function Edit({ commodity, units }) {
    return (
        <>
            <Head title={`${commodity.name} — SiagaPasok`} />

            <AdminLayout
                pageTitle={commodity.name}
                pageDescription={`Komoditas • ${commodity.code}`}
            >
                <div className="max-w-3xl">
                    <CommodityForm
                        commodity={commodity}
                        units={units}
                    />
                </div>
            </AdminLayout>
        </>
    );
}