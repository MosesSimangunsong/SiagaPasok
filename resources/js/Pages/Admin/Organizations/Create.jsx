import AdminLayout from "@/Layouts/AdminLayout";
import OrganizationForm from "./OrganizationForm";
import { Head } from "@inertiajs/react";

export default function Create({ organizationTypes }) {
    return (
        <>
            <Head title="Tambah Organisasi — SiagaPasok" />

            <AdminLayout
                pageTitle="Tambah Organisasi"
                pageDescription="Tambahkan SPPG atau KDKMP ke dalam platform."
            >
                <div className="max-w-4xl">
                    <OrganizationForm
                        organizationTypes={organizationTypes}
                    />
                </div>
            </AdminLayout>
        </>
    );
}