import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Checkbox from '@/Components/Checkbox';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function RolePicker({ roles, selected, onToggle }) {
    return (
        <div className="mt-2 grid grid-cols-2 gap-2">
            {roles.map((r) => (
                <label
                    key={r.id}
                    className="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                >
                    <Checkbox
                        checked={selected.includes(r.id)}
                        onChange={() => onToggle(r.id)}
                    />
                    {r.label}
                </label>
            ))}
        </div>
    );
}

export default function Index({ users, roles, employees }) {
    const flash = usePage().props.flash;
    const [open, setOpen] = useState(false); // create
    const [editing, setEditing] = useState(null); // user being edited
    const [resetting, setResetting] = useState(null); // user whose pw is reset
    const meId = usePage().props.auth?.user?.id;

    const create = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        employee_id: '',
        roles: [],
    });

    const edit = useForm({
        name: '',
        email: '',
        employee_id: '',
        roles: [],
    });

    const pw = useForm({ password: '', password_confirmation: '' });

    const toggleCreateRole = (id) =>
        create.setData(
            'roles',
            create.data.roles.includes(id)
                ? create.data.roles.filter((r) => r !== id)
                : [...create.data.roles, id],
        );

    const toggleEditRole = (id) =>
        edit.setData(
            'roles',
            edit.data.roles.includes(id)
                ? edit.data.roles.filter((r) => r !== id)
                : [...edit.data.roles, id],
        );

    const submitCreate = (e) => {
        e.preventDefault();
        create.post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: () => {
                create.reset();
                setOpen(false);
            },
        });
    };

    const openEdit = (u) => {
        edit.setData({
            name: u.name,
            email: u.email,
            employee_id: u.employee_id ?? '',
            roles: u.role_ids ?? [],
        });
        setEditing(u);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        edit.patch(route('admin.users.update', editing.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    const submitReset = (e) => {
        e.preventDefault();
        pw.patch(route('admin.users.password', resetting.id), {
            preserveScroll: true,
            onSuccess: () => {
                pw.reset();
                setResetting(null);
            },
        });
    };

    const toggleActive = (u) => {
        const verb = u.is_active ? 'Deactivate' : 'Activate';
        if (confirm(`${verb} ${u.name}?`)) {
            router.patch(route('admin.users.toggle', u.id), {}, { preserveScroll: true });
        }
    };

    // Employees available to link: unlinked ones, plus the one already linked
    // to the user being edited (so it shows in the dropdown).
    const editEmployeeOptions = editing
        ? [
              ...(editing.employee_id
                  ? [{ id: editing.employee_id, name: `${editing.employee} (current)` }]
                  : []),
              ...employees,
          ]
        : employees;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-slate-800">
                    User accounts
                </h2>
            }
        >
            <Head title="Users" />

            <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
                        {flash.success}
                    </div>
                )}

                <div className="mb-6 flex items-end justify-between">
                    <p className="max-w-prose text-sm text-slate-500">
                        Every login is tied to a person and one or more roles.
                        Create, edit, reset passwords, or deactivate accounts here.
                    </p>
                    <PrimaryButton onClick={() => setOpen(true)}>
                        Add user
                    </PrimaryButton>
                </div>

                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-6 py-3">Name</th>
                                <th className="px-6 py-3">Email</th>
                                <th className="px-6 py-3">Roles</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {users.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-10 text-center text-slate-400">
                                        No users yet. Add the first one to get started.
                                    </td>
                                </tr>
                            )}
                            {users.map((u) => (
                                <tr key={u.id} className="hover:bg-slate-50">
                                    <td className="px-6 py-3 font-medium text-slate-800">
                                        {u.name}
                                        {u.employee && (
                                            <span className="ml-2 text-xs text-slate-400">
                                                {u.employee}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-6 py-3 text-slate-600">{u.email}</td>
                                    <td className="px-6 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {u.roles.length === 0 && (
                                                <span className="text-slate-400">—</span>
                                            )}
                                            {u.roles.map((r) => (
                                                <span
                                                    key={r}
                                                    className="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-100"
                                                >
                                                    {r}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-6 py-3">
                                        <span
                                            className={
                                                u.is_active
                                                    ? 'inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700'
                                                    : 'inline-flex items-center gap-1.5 text-xs font-medium text-slate-400'
                                            }
                                        >
                                            <span
                                                className={
                                                    'h-1.5 w-1.5 rounded-full ' +
                                                    (u.is_active ? 'bg-emerald-500' : 'bg-slate-300')
                                                }
                                            />
                                            {u.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-3">
                                        <div className="flex items-center justify-end gap-3 text-xs font-medium">
                                            <button
                                                onClick={() => openEdit(u)}
                                                className="text-blue-600 hover:text-blue-500"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() => setResetting(u)}
                                                className="text-slate-600 hover:text-slate-500"
                                            >
                                                Reset password
                                            </button>
                                            {u.id !== meId && (
                                                <button
                                                    onClick={() => toggleActive(u)}
                                                    className={
                                                        u.is_active
                                                            ? 'text-red-600 hover:text-red-500'
                                                            : 'text-emerald-600 hover:text-emerald-500'
                                                    }
                                                >
                                                    {u.is_active ? 'Deactivate' : 'Activate'}
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Create */}
            <Modal show={open} onClose={() => setOpen(false)} maxWidth="lg">
                <form onSubmit={submitCreate} className="p-6">
                    <h3 className="text-lg font-semibold text-slate-800">Add a user</h3>
                    <p className="mt-1 text-sm text-slate-500">
                        They'll sign in with the email and password you set here.
                    </p>

                    <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="name" value="Full name" />
                            <TextInput id="name" className="mt-1 block w-full" value={create.data.name}
                                onChange={(e) => create.setData('name', e.target.value)} autoComplete="off" />
                            <InputError message={create.errors.name} className="mt-1" />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="email" value="Email" />
                            <TextInput id="email" type="email" className="mt-1 block w-full" value={create.data.email}
                                onChange={(e) => create.setData('email', e.target.value)} autoComplete="off" />
                            <InputError message={create.errors.email} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="password" value="Password" />
                            <TextInput id="password" type="password" className="mt-1 block w-full" value={create.data.password}
                                onChange={(e) => create.setData('password', e.target.value)} autoComplete="new-password" />
                            <InputError message={create.errors.password} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                            <TextInput id="password_confirmation" type="password" className="mt-1 block w-full"
                                value={create.data.password_confirmation}
                                onChange={(e) => create.setData('password_confirmation', e.target.value)} autoComplete="new-password" />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="employee_id" value="Link to employee (optional)" />
                            <select id="employee_id"
                                className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                value={create.data.employee_id}
                                onChange={(e) => create.setData('employee_id', e.target.value)}>
                                <option value="">— none —</option>
                                {employees.map((e) => (
                                    <option key={e.id} value={e.id}>{e.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel value="Roles" />
                            <RolePicker roles={roles} selected={create.data.roles} onToggle={toggleCreateRole} />
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={() => setOpen(false)}>Cancel</SecondaryButton>
                        <PrimaryButton disabled={create.processing}>Create user</PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Edit */}
            <Modal show={!!editing} onClose={() => setEditing(null)} maxWidth="lg">
                {editing && (
                    <form onSubmit={submitEdit} className="p-6">
                        <h3 className="text-lg font-semibold text-slate-800">Edit {editing.name}</h3>
                        <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="e_name" value="Full name" />
                                <TextInput id="e_name" className="mt-1 block w-full" value={edit.data.name}
                                    onChange={(e) => edit.setData('name', e.target.value)} />
                                <InputError message={edit.errors.name} className="mt-1" />
                            </div>
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="e_email" value="Email" />
                                <TextInput id="e_email" type="email" className="mt-1 block w-full" value={edit.data.email}
                                    onChange={(e) => edit.setData('email', e.target.value)} />
                                <InputError message={edit.errors.email} className="mt-1" />
                            </div>
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="e_employee" value="Linked employee" />
                                <select id="e_employee"
                                    className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    value={edit.data.employee_id}
                                    onChange={(e) => edit.setData('employee_id', e.target.value)}>
                                    <option value="">— none —</option>
                                    {editEmployeeOptions.map((e) => (
                                        <option key={e.id} value={e.id}>{e.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="sm:col-span-2">
                                <InputLabel value="Roles" />
                                <RolePicker roles={roles} selected={edit.data.roles} onToggle={toggleEditRole} />
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-3">
                            <SecondaryButton type="button" onClick={() => setEditing(null)}>Cancel</SecondaryButton>
                            <PrimaryButton disabled={edit.processing}>Save changes</PrimaryButton>
                        </div>
                    </form>
                )}
            </Modal>

            {/* Reset password */}
            <Modal show={!!resetting} onClose={() => setResetting(null)} maxWidth="md">
                {resetting && (
                    <form onSubmit={submitReset} className="p-6">
                        <h3 className="text-lg font-semibold text-slate-800">
                            Reset password — {resetting.name}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Set a new password and share it with them; they can change it after logging in.
                        </p>
                        <div className="mt-6 space-y-4">
                            <div>
                                <InputLabel htmlFor="r_password" value="New password" />
                                <TextInput id="r_password" type="password" className="mt-1 block w-full"
                                    value={pw.data.password}
                                    onChange={(e) => pw.setData('password', e.target.value)} autoComplete="new-password" />
                                <InputError message={pw.errors.password} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="r_confirm" value="Confirm password" />
                                <TextInput id="r_confirm" type="password" className="mt-1 block w-full"
                                    value={pw.data.password_confirmation}
                                    onChange={(e) => pw.setData('password_confirmation', e.target.value)} autoComplete="new-password" />
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-3">
                            <SecondaryButton type="button" onClick={() => setResetting(null)}>Cancel</SecondaryButton>
                            <PrimaryButton disabled={pw.processing}>Reset password</PrimaryButton>
                        </div>
                    </form>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
