import { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, useForm, usePage, router } from '@inertiajs/react';

/**
 * Admin → Holidays. 6.C's working-day count skips every date on this list,
 * so each year's proclamation (and the separate Eid proclamations) is
 * entered here — no deploy needed.
 */
export default function Index({ holidays }) {
    const flash = usePage().props.flash;

    const { data, setData, post, processing, errors, reset } = useForm({
        date: '',
        name: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.holidays.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const remove = (h) => {
        if (
            confirm(
                `Remove ${h.name} (${h.label})? New leave applications will count it as a working day again.`,
            )
        ) {
            router.delete(route('admin.holidays.destroy', h.id), {
                preserveScroll: true,
            });
        }
    };

    // Newest year first; the controller already sorts by date descending.
    const byYear = useMemo(() => {
        const groups = new Map();
        holidays.forEach((h) => {
            if (!groups.has(h.year)) groups.set(h.year, []);
            groups.get(h.year).push(h);
        });
        return [...groups.entries()];
    }, [holidays]);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Holidays
                </h2>
            }
        >
            <Head title="Holidays" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200">
                            {flash.success}
                        </div>
                    )}

                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <header className="mb-4">
                            <h3 className="text-base font-semibold text-gray-900">
                                Add a non-working day
                            </h3>
                            <p className="mt-1 text-sm text-gray-600">
                                Regular holidays and special (non-working) days
                                both go here — 6.C skips them when counting
                                working days. Add next year&rsquo;s dates once
                                the proclamation is out. Special{' '}
                                <span className="font-medium">working</span>{' '}
                                days (like EDSA day 2026) are not holidays — do
                                not add them.
                            </p>
                        </header>

                        <form
                            onSubmit={submit}
                            className="flex flex-wrap items-end gap-4"
                        >
                            <div>
                                <InputLabel htmlFor="holiday-date" value="Date" />
                                <TextInput
                                    id="holiday-date"
                                    type="date"
                                    className="mt-1 block"
                                    value={data.date}
                                    onChange={(e) => setData('date', e.target.value)}
                                />
                                <InputError message={errors.date} className="mt-1" />
                            </div>
                            <div className="min-w-64 flex-1">
                                <InputLabel htmlFor="holiday-name" value="Name" />
                                <TextInput
                                    id="holiday-name"
                                    className="mt-1 block w-full"
                                    placeholder="e.g. Araw ng Kagitingan"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </div>
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Adding…' : 'Add holiday'}
                            </PrimaryButton>
                        </form>
                    </section>

                    {byYear.map(([year, list]) => (
                        <section
                            key={year}
                            className="bg-white p-6 shadow-sm sm:rounded-lg"
                        >
                            <h3 className="mb-3 text-base font-semibold text-gray-900">
                                {year}{' '}
                                <span className="text-sm font-normal text-gray-500">
                                    · {list.length} non-working day
                                    {list.length === 1 ? '' : 's'}
                                </span>
                            </h3>
                            <div className="overflow-x-auto">
                            <table className="w-full min-w-[22rem] text-sm">
                                <tbody>
                                    {list.map((h) => (
                                        <tr
                                            key={h.id}
                                            className="border-t border-gray-100"
                                        >
                                            <td className="w-44 whitespace-nowrap py-2 pr-3 text-gray-600">
                                                {h.label}
                                            </td>
                                            <td className="py-2 font-medium text-gray-900">
                                                {h.name}
                                            </td>
                                            <td className="py-2 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => remove(h)}
                                                    className="text-xs font-medium text-red-600 hover:text-red-500"
                                                >
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            </div>
                        </section>
                    ))}

                    {holidays.length === 0 && (
                        <p className="text-sm text-gray-500">
                            No holidays yet — run the seeder or add them above.
                        </p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
