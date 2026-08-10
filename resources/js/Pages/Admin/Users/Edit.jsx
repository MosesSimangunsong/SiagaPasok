import AdminLayout from "@/Layouts/AdminLayout";
import UserForm from "./UserForm";
import { Head } from "@inertiajs/react";

export default function Edit({ user, organizations, roles }) {
    return (
        <>
            <Head title={`${user.name} — SiagaPasok`} />

            <AdminLayout
                pageTitle={user.name}
                pageDescription={`${user.email} • ${user.role_label}`}
            >
                <div className="max-w-4xl">
                    <UserForm
                        user={user}
                        organizations={organizations}
                        roles={roles}
                    />
                </div>
            </AdminLayout>
        </>
    );
}