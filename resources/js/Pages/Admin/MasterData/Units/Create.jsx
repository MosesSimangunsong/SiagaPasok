import AdminLayout from "@/Layouts/AdminLayout";
import UnitForm from "./UnitForm";
import { Head } from "@inertiajs/react";

export default function Create() {
    return (
        <>
            <Head title="Tambah Unit — SiagaPasok" />

            <AdminLayout
                pageTitle="Tambah Unit"
                pageDescription="Tambahkan unit pengukuran baru ke master data."
            >
                <div className="max-w-3xl">
                    <UnitForm />
                </div>
            </AdminLayout>
        </>
    );
}