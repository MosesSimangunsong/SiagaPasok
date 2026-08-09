import { Head } from '@inertiajs/react';

export default function Foundation() {
    return (
        <>
            <Head title="SiagaPasok" />

            <main className="flex min-h-screen items-center justify-center bg-slate-50 px-6">
                <section className="w-full max-w-xl rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <p className="text-sm font-medium text-blue-600">
                        SiagaPasok
                    </p>

                    <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">
                        Frontend Foundation
                    </h1>

                    <p className="mt-3 text-sm leading-6 text-slate-600">
                        Laravel, Inertia, React, Vite, dan Tailwind telah terhubung
                        sebagai fondasi aplikasi.
                    </p>
                </section>
            </main>
        </>
    );
}