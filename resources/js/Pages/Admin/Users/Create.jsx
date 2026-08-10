import AdminLayout from "@/Layouts/AdminLayout";
import UserForm from "./UserForm";
import { Head } from "@inertiajs/react";

export default function Create({
    organizations,
    roles,
    selectedOrganizationId,
}) {
    return (
        <>
            <Head title="Tambah Pengguna — SiagaPasok" />

            <AdminLayout
                pageTitle="Tambah Pengguna"
                pageDescription="Buat akun baru dan tetapkan role serta organisasi."
            >
                <div className="max-w-4xl">
                    <UserForm
                        organizations={organizations}
                        roles={roles}
                        selectedOrganizationId={
                            selectedOrganizationId
                        }
                    />
                </div>
            </AdminLayout>
        </>
    );
}