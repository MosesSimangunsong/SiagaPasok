import KdkmpLayout from "@/Layouts/KdkmpLayout";
import { Head } from "@inertiajs/react";
import ProducerForm from "./ProducerForm";

export default function Create() {
    return (
        <>
            <Head title="Tambah Produsen — SiagaPasok" />

            <KdkmpLayout
                pageTitle="Tambah Produsen"
                pageDescription="Tambahkan produsen ke registry internal KDKMP."
            >
                <div className="max-w-4xl">
                    <ProducerForm />
                </div>
            </KdkmpLayout>
        </>
    );
}