import AdminLayout from "@/Layouts/AdminLayout";
import CommodityForm from "./CommodityForm";
import { Head } from "@inertiajs/react";

export default function Create({ units }) {
    return (
        <>
            <Head title="Tambah Komoditas — SiagaPasok" />

            <AdminLayout
                pageTitle="Tambah Komoditas"
                pageDescription="Tambahkan commodity master baru."
            >
                <div className="max-w-3xl">
                    <CommodityForm units={units} />
                </div>
            </AdminLayout>
        </>
    );
}