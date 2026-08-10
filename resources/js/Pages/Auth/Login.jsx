import { Head, useForm } from "@inertiajs/react";
import {
    ArrowRight,
    LoaderCircle,
    LockKeyhole,
    Network,
    ShieldCheck,
} from "lucide-react";

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
    });

    const submit = (event) => {
        event.preventDefault();

        post("/login", {
            preserveScroll: true,
            onFinish: () => reset("password"),
        });
    };

    return (
        <>
            <Head title="Masuk — SiagaPasok" />

            <main className="min-h-screen bg-[#F8FAFC] text-[#0F172A]">
                <div className="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
                    <section className="relative hidden overflow-hidden bg-[#0B1F35] px-12 py-12 text-white lg:flex lg:flex-col lg:justify-between">
                        <div
                            className="absolute inset-0 opacity-[0.08]"
                            style={{
                                backgroundImage:
                                    "radial-gradient(circle at 1px 1px, white 1px, transparent 0)",
                                backgroundSize: "28px 28px",
                            }}
                        />

                        <div className="relative">
                            <div className="inline-flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-xl bg-[#2563EB]">
                                    <Network className="size-5" />
                                </div>

                                <span className="text-xl font-semibold tracking-tight">
                                    SiagaPasok
                                </span>
                            </div>
                        </div>

                        <div className="relative max-w-xl">
                            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-sm text-slate-200">
                                <ShieldCheck className="size-4" />
                                Pre-Procurement Supply Orchestration
                            </div>

                            <h1 className="max-w-lg text-4xl font-semibold leading-tight tracking-[-0.03em] xl:text-5xl">
                                Pasokan Lokal Siap Sebelum Pengadaan.
                            </h1>

                            <p className="mt-5 max-w-lg text-base leading-7 text-slate-300">
                                Visibilitas bersama untuk mengoordinasikan kebutuhan
                                SPPG, kapasitas produsen lokal, risiko pasokan, dan
                                kesiapan sebelum proses pengadaan resmi dimulai.
                            </p>
                        </div>

                        <div className="relative text-sm text-slate-400">
                            Sistem internal SiagaPasok
                        </div>
                    </section>

                    <section className="flex items-center justify-center px-6 py-12 sm:px-10 lg:px-16">
                        <div className="w-full max-w-md">
                            <div className="mb-8 lg:hidden">
                                <div className="inline-flex items-center gap-3">
                                    <div className="flex size-10 items-center justify-center rounded-xl bg-[#2563EB] text-white">
                                        <Network className="size-5" />
                                    </div>

                                    <span className="text-xl font-semibold tracking-tight text-[#0B1F35]">
                                        SiagaPasok
                                    </span>
                                </div>
                            </div>

                            <div className="mb-8">
                                <div className="mb-4 flex size-11 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm">
                                    <LockKeyhole className="size-5 text-[#2563EB]" />
                                </div>

                                <h2 className="text-3xl font-semibold tracking-[-0.03em] text-slate-950">
                                    Masuk ke SiagaPasok
                                </h2>

                                <p className="mt-2 text-sm leading-6 text-slate-500">
                                    Gunakan akun yang telah dibuat oleh administrator.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-5">
                                <div>
                                    <label
                                        htmlFor="email"
                                        className="mb-2 block text-sm font-medium text-slate-700"
                                    >
                                        Email
                                    </label>

                                    <input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(event) =>
                                            setData("email", event.target.value)
                                        }
                                        autoComplete="username"
                                        autoFocus
                                        required
                                        className="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-[#2563EB] focus:ring-4 focus:ring-blue-100"
                                        placeholder="nama@organisasi.id"
                                    />

                                    {errors.email && (
                                        <p className="mt-2 text-sm font-medium text-red-700">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label
                                        htmlFor="password"
                                        className="mb-2 block text-sm font-medium text-slate-700"
                                    >
                                        Kata sandi
                                    </label>

                                    <input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(event) =>
                                            setData("password", event.target.value)
                                        }
                                        autoComplete="current-password"
                                        required
                                        className="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-[#2563EB] focus:ring-4 focus:ring-blue-100"
                                        placeholder="Masukkan kata sandi"
                                    />

                                    {errors.password && (
                                        <p className="mt-2 text-sm font-medium text-red-700">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-[#2563EB] px-4 text-sm font-semibold text-white transition hover:bg-[#1D4ED8] focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {processing ? (
                                        <>
                                            <LoaderCircle className="size-4 animate-spin" />
                                            Memproses...
                                        </>
                                    ) : (
                                        <>
                                            Masuk
                                            <ArrowRight className="size-4" />
                                        </>
                                    )}
                                </button>
                            </form>

                            <div className="mt-8 border-t border-slate-200 pt-6">
                                <p className="text-xs leading-5 text-slate-500">
                                    Tidak ada pendaftaran publik. Akun dan hak akses
                                    dikelola oleh System Admin SiagaPasok.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}