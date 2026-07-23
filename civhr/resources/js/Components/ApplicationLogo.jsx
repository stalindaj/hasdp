/**
 * The CivDir brand block: the unit seal beside the wordmark.
 * `compact` renders the seal alone (e.g. tight mobile headers).
 */
export default function ApplicationLogo({ compact = false, className = '' }) {
    return (
        <span className={`flex items-center gap-2.5 ${className}`}>
            <img
                src="/images/agency-logo.png"
                alt="15th Strike Wing seal"
                className="h-9 w-9 shrink-0 rounded-full object-contain"
                onError={(e) => (e.currentTarget.style.display = 'none')}
            />
            {!compact && (
                <span className="leading-tight">
                    <span className="block text-base font-bold tracking-tight text-slate-900">
                        CivDir
                    </span>
                    <span className="block text-[10px] font-medium uppercase tracking-widest text-slate-500">
                        Civilian's Directory
                    </span>
                </span>
            )}
        </span>
    );
}
