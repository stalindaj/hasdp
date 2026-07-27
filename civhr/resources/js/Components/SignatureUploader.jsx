import { useForm, router } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

/**
 * Upload the scan of a wet signature. It prints centred over the person's
 * name on CS Form No. 6 — 6.D for the applicant, 7.A for the HR officer,
 * 7.C/7.D for the approving official.
 *
 * `userId` is whose signature this is; admins pass someone else's.
 */
export default function SignatureUploader({ userId, url, label = 'My signature', compact = false }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        signature: null,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('signature.store', userId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const remove = () => {
        if (confirm('Remove this signature? It will stop printing on new forms.')) {
            router.delete(route('signature.destroy', userId), { preserveScroll: true });
        }
    };

    return (
        <div>
            {!compact && (
                <h3 className="text-base font-semibold text-gray-900">{label}</h3>
            )}

            {url ? (
                <div className="mt-2 flex flex-wrap items-center gap-4">
                    {/* Checkerboard shows whether the background is transparent. */}
                    <img
                        src={url}
                        alt="Signature"
                        className="h-16 max-w-[16rem] object-contain"
                        style={{
                            backgroundImage:
                                'linear-gradient(45deg,#eee 25%,transparent 25%),linear-gradient(-45deg,#eee 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#eee 75%),linear-gradient(-45deg,transparent 75%,#eee 75%)',
                            backgroundSize: '12px 12px',
                            backgroundPosition: '0 0,0 6px,6px -6px,-6px 0',
                        }}
                    />
                    <button
                        type="button"
                        onClick={remove}
                        className="text-xs font-medium text-red-600 hover:text-red-500"
                    >
                        Remove
                    </button>
                </div>
            ) : (
                <p className="mt-1 text-sm text-gray-600">
                    No signature on file — the name still prints, with a blank
                    line to sign by pen.
                </p>
            )}

            <form onSubmit={submit} className="mt-3 flex flex-wrap items-center gap-3">
                <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    className="block text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                    onChange={(e) => setData('signature', e.target.files[0] ?? null)}
                />
                <PrimaryButton disabled={processing || !data.signature}>
                    {processing ? 'Saving…' : url ? 'Replace' : 'Upload'}
                </PrimaryButton>
                <InputError message={errors.signature} />
            </form>

            <p className="mt-2 text-xs text-gray-500">
                Sign on white paper, photograph it, and crop close. A PNG with a
                transparent background prints cleanest · max 2&nbsp;MB.
            </p>
        </div>
    );
}
