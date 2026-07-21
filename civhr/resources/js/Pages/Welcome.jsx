import { Head, Link, usePage } from '@inertiajs/react';

function Feature({ icon, title, children }) {
    return (
        <div className="group rounded-2xl border border-slate-200/80 bg-white/70 p-6 shadow-sm backdrop-blur transition duration-200 hover:-translate-y-1 hover:border-blue-200 hover:shadow-lg hover:shadow-blue-900/5">
            <div className="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600/10 text-blue-700 transition group-hover:bg-blue-600 group-hover:text-white">
                {icon}
            </div>
            <h3 className="text-base font-semibold text-slate-900">{title}</h3>
            <p className="mt-1.5 text-sm leading-relaxed text-slate-600">
                {children}
            </p>
        </div>
    );
}

export default function Welcome({ canLogin, agency }) {
    const user = usePage().props.auth?.user;
    const primaryHref = user ? route('leave.index') : route('login');

    return (
        <>
            <Head title="CivDir — Civilian's Directory" />

            <div className="relative min-h-screen overflow-hidden bg-gradient-to-b from-slate-50 to-white text-slate-900 antialiased">
                {/* Decorative background */}
                <div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
                    <div className="absolute -right-40 -top-40 h-[32rem] w-[32rem] rounded-full bg-blue-300/25 blur-3xl" />
                    <div className="absolute -bottom-48 -left-40 h-[32rem] w-[32rem] rounded-full bg-amber-300/20 blur-3xl" />
                    <div
                        className="absolute inset-0 opacity-40"
                        style={{
                            backgroundImage:
                                'radial-gradient(circle at 1px 1px, rgb(15 23 42 / 0.05) 1px, transparent 0)',
                            backgroundSize: '28px 28px',
                        }}
                    />
                </div>

                {/* Top bar */}
                <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                    <div className="flex items-center gap-3">
                        <div className="flex -space-x-2">
                            {agency.logoLeft && (
                                <img
                                    src={agency.logoLeft}
                                    alt=""
                                    className="h-10 w-10 rounded-full bg-white object-contain ring-1 ring-slate-200"
                                />
                            )}
                            {agency.logoRight && (
                                <img
                                    src={agency.logoRight}
                                    alt=""
                                    className="h-10 w-10 rounded-full bg-white object-contain ring-1 ring-slate-200"
                                />
                            )}
                        </div>
                        <div className="leading-tight">
                            <p className="text-sm font-semibold text-slate-900">
                                {agency.name}
                            </p>
                            <p className="text-xs text-slate-500">
                                {agency.address}
                            </p>
                        </div>
                    </div>

                    {canLogin && (
                        <Link
                            href={user ? route('my-profile.edit') : route('login')}
                            className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-700"
                        >
                            {user ? 'Go to app' : 'Log in'}
                        </Link>
                    )}
                </header>

                {/* Hero */}
                <main className="mx-auto max-w-6xl px-6">
                    <section className="grid items-center gap-12 py-14 lg:grid-cols-2 lg:py-20">
                        <div>
                            <span className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm">
                                <span className="relative flex h-2 w-2">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                    <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                                </span>
                                15th Strike Wing · Civilian Systems
                            </span>

                            <h1 className="mt-6 text-4xl font-bold leading-[1.1] tracking-tight sm:text-5xl">
                                CivDir
                                <br />
                                <span className="bg-gradient-to-r from-blue-700 via-blue-600 to-sky-500 bg-clip-text text-transparent">
                                    Civilian's Directory
                                </span>
                            </h1>

                            <p className="mt-5 max-w-md text-base leading-relaxed text-slate-600">
                                One login for the wing's civilian systems — file
                                and approve leave, keep employee records current,
                                and print a signature-ready CS Form No. 6.
                            </p>

                            <div className="mt-8 flex flex-wrap items-center gap-4">
                                <Link
                                    href={primaryHref}
                                    className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:-translate-y-0.5 hover:bg-blue-500"
                                >
                                    {user ? 'Open dashboard' : 'Log in to continue'}
                                    <svg
                                        className="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        strokeWidth="2"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                                        />
                                    </svg>
                                </Link>
                                <span className="text-sm text-slate-500">
                                    Authorized personnel only
                                </span>
                            </div>
                        </div>

                        {/* Seal card */}
                        <div className="relative">
                            <div className="absolute inset-0 -z-10 rounded-[2rem] bg-gradient-to-tr from-blue-600/10 to-amber-400/10 blur-2xl" />
                            <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-slate-200/80 bg-white/80 p-10 text-center shadow-xl shadow-slate-900/5 backdrop-blur">
                                <div className="flex items-center gap-6">
                                    {agency.logoLeft && (
                                        <img
                                            src={agency.logoLeft}
                                            alt=""
                                            className="h-20 w-20 object-contain drop-shadow"
                                        />
                                    )}
                                    {agency.logoRight && (
                                        <img
                                            src={agency.logoRight}
                                            alt=""
                                            className="h-20 w-20 object-contain drop-shadow"
                                        />
                                    )}
                                </div>
                                <p className="mt-7 text-[0.7rem] font-medium uppercase tracking-[0.2em] text-slate-400">
                                    Republic of the Philippines
                                </p>
                                <p className="mt-1 text-lg font-bold text-slate-900">
                                    {agency.name}
                                </p>
                                <p className="mt-0.5 text-sm text-slate-500">
                                    {agency.address}
                                </p>
                                {agency.address2 && (
                                    <p className="text-sm text-slate-500">
                                        {agency.address2}
                                    </p>
                                )}
                            </div>
                        </div>
                    </section>

                    {/* Features */}
                    <section className="grid gap-6 pb-20 sm:grid-cols-3">
                        <Feature
                            title="Employee profiles"
                            icon={
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 19.5a7.5 7.5 0 0 1 15 0v.75H4.5v-.75Z" />
                                </svg>
                            }
                        >
                            Keep your official record and personal details in
                            one place — auto-filled onto every form you file.
                        </Feature>
                        <Feature
                            title="Leave applications"
                            icon={
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z" />
                                </svg>
                            }
                        >
                            File the CS Form No. 6 in a few clicks, with the
                            details section that matches your leave type.
                        </Feature>
                        <Feature
                            title="Approve &amp; print"
                            icon={
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.4 42.4 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48 48 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48 48 0 0 1 1.913-.247m10.5 0a48.7 48.7 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659" />
                                </svg>
                            }
                        >
                            Admins certify credits and approve, then the printed
                            form comes out ready for wet signature.
                        </Feature>
                    </section>
                </main>

                <footer className="border-t border-slate-200/80 py-6">
                    <p className="mx-auto max-w-6xl px-6 text-center text-xs text-slate-500">
                        {agency.name} · {agency.address}
                        {agency.address2 ? `, ${agency.address2}` : ''}
                    </p>
                </footer>
            </div>
        </>
    );
}
