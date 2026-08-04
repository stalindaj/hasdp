import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * The 15SW personnel-portal look, ported from the standalone portal's
 * home.php: status ticker, HUD hero card with the radar sweep, Quick Access
 * modules, and dark panels/tables for whatever a dashboard needs to show.
 *
 * Dashboards only. Forms stay on the white app chrome — a data-entry sheet
 * (leave, IPCR, IWOT) is easier to read and to print light.
 *
 * Self-contained: system fonts and one inline <style>, no webfont or external
 * stylesheet, because the production host is a closed .mil.ph box.
 */

const CSS = `
.portal {
    --navy-deep:#050d18; --navy:#0a1a2f; --gold:#f0c94e; --gold-dim:#c9a030;
    --steel:#8ba3bf; --steel-dim:#5c7995; --muted:#9fb4cc;
    --mono: ui-monospace, 'Cascadia Mono', 'Segoe UI Mono', Consolas, monospace;
    position:relative; background:var(--navy-deep); color:#eef3f8; overflow:hidden;
    min-height:calc(100vh - 8rem);
}
/* Instrument-panel grid + a soft gold spotlight, as on the portal. */
.portal::before {
    content:''; position:absolute; inset:0; pointer-events:none;
    background-image:
        linear-gradient(rgba(240,201,78,.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(240,201,78,.05) 1px, transparent 1px);
    background-size:48px 48px; opacity:.5;
}
.portal::after {
    content:''; position:absolute; inset:0; pointer-events:none;
    background:radial-gradient(circle at 50% 22%, rgba(240,201,78,.13) 0%, transparent 45%);
}
.portal > * { position:relative; z-index:1; }
.portal a { text-decoration:none; }
.p-wrap { max-width:1180px; margin:0 auto; padding:0 1.25rem; width:100%; }

/* ── ticker ───────────────────────────────────────────────────────────── */
.p-ticker {
    display:flex; justify-content:center; gap:1.75rem; flex-wrap:wrap;
    font-family:var(--mono); font-size:.72rem; letter-spacing:.5px;
    color:var(--steel); padding:.4rem 1rem; border-bottom:1px solid rgba(240,201,78,.15);
}
.p-ticker .lbl { color:var(--gold-dim); }
.p-ticker .dot {
    display:inline-block; width:6px; height:6px; border-radius:50%;
    background:#3ddc84; box-shadow:0 0 6px #3ddc84; margin-right:6px;
    animation:p-pulse 2.2s ease-in-out infinite;
}
@keyframes p-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

/* ── hero ─────────────────────────────────────────────────────────────── */
.p-hero { display:flex; justify-content:center; padding:2.4rem 1.25rem 1.4rem; }
.p-hero.compact { padding:1.6rem 1.25rem 1rem; }
.p-card {
    position:relative; width:100%; max-width:780px; text-align:center;
    background:rgba(10,26,47,.55); border:1px solid rgba(240,201,78,.16);
    border-radius:10px; padding:2.4rem 2rem 1.8rem; box-shadow:0 20px 60px rgba(0,0,0,.7);
}
.p-card.wide { max-width:1180px; }
.p-card i.c { position:absolute; width:28px; height:28px; border:2px solid var(--gold); opacity:.85; }
.p-card i.tl { top:-1px; left:-1px; border-right:0; border-bottom:0; }
.p-card i.tr { top:-1px; right:-1px; border-left:0; border-bottom:0; }
.p-card i.bl { bottom:-1px; left:-1px; border-right:0; border-top:0; }
.p-card i.br { bottom:-1px; right:-1px; border-left:0; border-top:0; }

.p-badge {
    display:inline-flex; align-items:center; gap:8px; border:1px solid var(--gold);
    color:var(--gold); font-family:var(--mono); font-weight:600; font-size:.68rem;
    letter-spacing:2px; text-transform:uppercase; padding:.35rem 1.1rem;
    border-radius:3px; margin-bottom:1.4rem;
}

.p-avatar-wrap { position:relative; width:120px; height:120px; margin:0 auto 1.5rem; }
.p-avatar-wrap.sm, .p-avatar-wrap.sm .p-avatar { width:76px; height:76px; }
.p-avatar-wrap.sm .p-avatar { font-size:1.5rem; }
.p-radar { position:absolute; inset:-14px; border-radius:50%; border:1px solid rgba(240,201,78,.25); }
.p-radar::before {
    content:''; position:absolute; inset:0; border-radius:50%;
    background:conic-gradient(from 0deg, transparent 0deg, rgba(240,201,78,.55) 18deg, transparent 55deg);
    animation:p-radar 4.5s linear infinite;
}
.p-radar::after { content:''; position:absolute; inset:10px; border-radius:50%; border:1px dashed rgba(240,201,78,.18); }
@keyframes p-radar { to { transform:rotate(360deg); } }
.p-avatar {
    position:relative; width:120px; height:120px; border-radius:50%; z-index:1;
    display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#16324f,#0a1a2f); color:#fff;
    font-size:2.4rem; font-weight:700; letter-spacing:1px;
    box-shadow:0 0 0 4px var(--gold), 0 8px 30px rgba(0,0,0,.6);
}

.p-title {
    font-size:clamp(1.5rem,4vw,2.6rem); font-weight:700; line-height:1.15;
    text-transform:uppercase; letter-spacing:.5px; color:#fff; margin:0;
}
.p-title.sm { font-size:clamp(1.2rem,2.6vw,1.7rem); }
.p-title span { color:var(--gold); text-shadow:0 0 30px rgba(240,201,78,.25); }
.p-sub {
    font-family:var(--mono); color:var(--steel); font-size:.82rem; letter-spacing:1px;
    text-transform:uppercase; margin:.8rem auto 1.6rem;
}
.p-sub b { color:var(--gold); font-weight:400; }

.p-meta {
    display:flex; justify-content:center; gap:2.2rem; flex-wrap:wrap;
    font-family:var(--mono); font-size:.78rem;
    border-top:1px solid rgba(240,201,78,.15); padding-top:1.2rem;
}
.p-meta > div { display:flex; flex-direction:column; align-items:center; gap:3px; }
.p-meta .k { color:var(--steel-dim); font-size:.62rem; text-transform:uppercase; letter-spacing:1.5px; }

/* ── section label ────────────────────────────────────────────────────── */
.p-label {
    display:flex; align-items:center; justify-content:center; gap:.8rem;
    font-family:var(--mono); color:var(--steel-dim); font-size:.72rem;
    text-transform:uppercase; letter-spacing:3px; margin:0 0 1.2rem;
}
.p-label::before, .p-label::after { content:''; height:1px; flex:1; max-width:80px; background:rgba(240,201,78,.25); }

/* ── quick access ─────────────────────────────────────────────────────── */
.p-modules { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); }
.p-mod {
    position:relative; display:block; color:#fff;
    background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.07);
    border-left:3px solid rgba(240,201,78,.35); border-radius:8px;
    padding:1.4rem 1.2rem; transition:all .25s ease;
}
.p-mod:hover {
    background:rgba(240,201,78,.07); border-left-color:var(--gold);
    border-color:rgba(240,201,78,.3); transform:translateY(-6px);
    box-shadow:0 12px 40px rgba(0,0,0,.4);
}
.p-mod .tag { font-family:var(--mono); font-size:.62rem; letter-spacing:1.5px; color:var(--gold-dim); display:block; margin-bottom:.7rem; }
.p-mod .ic {
    display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px;
    border-radius:8px; background:rgba(240,201,78,.12); font-size:1.3rem; margin-bottom:.8rem;
}
.p-mod:hover .ic { background:rgba(240,201,78,.22); }
.p-mod h3 { font-size:1.05rem; font-weight:600; letter-spacing:.5px; text-transform:uppercase; margin:0 0 .2rem; }
.p-mod p { color:var(--muted); font-size:.8rem; margin:0; }
.p-mod .go { position:absolute; top:1.2rem; right:1.1rem; color:var(--steel-dim); transition:all .2s ease; }
.p-mod:hover .go { transform:translate(3px,-3px); color:var(--gold); }

/* ── panels, stats, tables ────────────────────────────────────────────── */
.p-panel {
    background:rgba(10,26,47,.55); border:1px solid rgba(240,201,78,.14);
    border-radius:10px; padding:1.2rem 1.3rem;
}
/* A grid item defaults to min-width:auto, so the roster table's intrinsic
   width pushes its column wider than a phone screen — and .portal clips it.
   Letting the column shrink hands the scrolling back to the table's own box. */
.portal .grid > *, .p-panel { min-width:0; }
.p-panel > h3 {
    font-family:var(--mono); font-size:.7rem; letter-spacing:2px; text-transform:uppercase;
    color:var(--gold-dim); margin:0 0 .9rem; font-weight:600;
}
.p-stats { display:flex; flex-wrap:wrap; gap:1.5rem; align-items:flex-end; }
.p-stat .v { font-size:1.9rem; font-weight:700; color:#fff; line-height:1.1; }
.p-stat .l { font-family:var(--mono); font-size:.64rem; letter-spacing:.8px; color:var(--steel-dim); text-transform:uppercase; }

.p-table { width:100%; border-collapse:collapse; font-size:.85rem; }
.p-table thead th {
    font-family:var(--mono); font-size:.64rem; letter-spacing:1.5px; text-transform:uppercase;
    color:var(--gold-dim); text-align:left; padding:.7rem .9rem; font-weight:600;
    border-bottom:1px solid rgba(240,201,78,.2); white-space:nowrap;
}
.p-table tbody td { padding:.65rem .9rem; border-bottom:1px solid rgba(255,255,255,.06); vertical-align:top; }
.p-table tbody tr:hover { background:rgba(240,201,78,.05); }
.p-table a { color:#eef3f8; }
.p-table a:hover { color:var(--gold); }
.p-table .dim { color:var(--steel-dim); font-size:.72rem; }
.p-table .warn { color:var(--gold); font-size:.72rem; font-weight:600; }
.p-table .ok { color:#3ddc84; font-size:.72rem; font-weight:600; }

.p-chip {
    display:inline-flex; align-items:center; justify-content:center; min-width:28px; height:28px;
    padding:0 .5rem; border-radius:999px; font-size:.75rem; font-weight:700;
    border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.05); color:var(--steel);
}
.p-chip.done { border-color:rgba(61,220,132,.5); background:rgba(61,220,132,.12); color:#3ddc84; }
button.p-chip { cursor:pointer; transition:all .15s ease; }
button.p-chip:hover { border-color:var(--gold); color:var(--gold); }

.p-link { color:var(--gold); font-size:.78rem; font-weight:600; background:none; border:0; padding:0; cursor:pointer; }
.p-link:hover { text-decoration:underline; }
.p-item { border:1px solid rgba(255,255,255,.08); border-radius:8px; padding:.6rem .7rem; display:block; }
a.p-item:hover { border-color:rgba(240,201,78,.4); background:rgba(240,201,78,.05); }
.p-empty { color:var(--steel-dim); font-size:.82rem; }

.p-strip {
    text-align:center; margin-top:2rem; padding:1.2rem 0 2rem;
    border-top:1px solid rgba(255,255,255,.06); font-family:var(--mono);
    color:var(--steel-dim); font-size:.7rem; letter-spacing:1px; text-transform:uppercase;
}

@media (prefers-reduced-motion: reduce) { .p-radar::before, .p-ticker .dot { animation:none; } }
@media (max-width:640px) {
    .p-hero { padding:1.4rem 1rem .8rem; }
    .p-card { padding:1.5rem 1rem; }
    .p-avatar-wrap, .p-avatar { width:84px; height:84px; }
    .p-avatar { font-size:1.7rem; }
    .p-ticker { gap:1rem; font-size:.65rem; }
    .p-meta { gap:1.3rem; }
}
`;

function initialsOf(name) {
    return (name || '')
        .replace(/,/g, ' ')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('');
}

/** The dark shell. Everything else here expects to sit inside it. */
export function Portal({ children }) {
    return (
        <div className="portal">
            <style>{CSS}</style>
            {children}
        </div>
    );
}

/** STATUS · DATE · TIME · CLEARANCE, with the clock ticking. */
export function PortalTicker({ clearance }) {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 30000);
        return () => clearInterval(id);
    }, []);

    return (
        <div className="p-ticker">
            <span><span className="dot" /><span className="lbl">STATUS</span> · SYSTEMS NOMINAL</span>
            <span><span className="lbl">DATE</span> · {fmtDate(now)}</span>
            <span>
                <span className="lbl">TIME</span> ·{' '}
                {now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false })} PHT
            </span>
            {clearance && <span><span className="lbl">CLEARANCE</span> · {clearance}</span>}
        </div>
    );
}

export const fmtDate = (d) =>
    d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

/**
 * The welcome card. `compact` shrinks it for the admin view, where the roster
 * — not the greeting — is the thing you came to look at.
 */
export function PortalHero({ name, designation, subtitle, compact = false, children }) {
    return (
        <section className={`p-hero${compact ? ' compact' : ''}`}>
            <div className={`p-card${compact ? ' wide' : ''}`}>
                <i className="c tl" /><i className="c tr" /><i className="c bl" /><i className="c br" />

                <div className="p-badge">🛡 15th Strike Wing</div>

                <div className={`p-avatar-wrap${compact ? ' sm' : ''}`}>
                    <div className="p-radar" />
                    <div className="p-avatar">{initialsOf(name)}</div>
                </div>

                <h1 className={`p-title${compact ? ' sm' : ''}`}>
                    Welcome, <span>{name}</span>
                </h1>

                <p className="p-sub"><b>»</b> {subtitle} <b>«</b></p>

                <div className="p-meta">
                    <div>
                        <span>{name}</span>
                        <span className="k">Personnel</span>
                    </div>
                    {designation && (
                        <div>
                            <span>{designation}</span>
                            <span className="k">Designation</span>
                        </div>
                    )}
                    <div>
                        <span>{fmtDate(new Date())}</span>
                        <span className="k">Today</span>
                    </div>
                </div>

                {children}
            </div>
        </section>
    );
}

/** The Quick Access module cards. */
export function PortalModules({ modules, label = 'Quick Access' }) {
    return (
        <div className="p-wrap" style={{ paddingTop: '.5rem', paddingBottom: '1.5rem' }}>
            <div className="p-label">{label}</div>
            <div className="p-modules">
                {modules.map((m, i) => (
                    <Link key={m.title} href={m.href} className="p-mod">
                        <span className="tag">{`MOD // ${String(i + 1).padStart(2, '0')}`}</span>
                        <span className="go">↗</span>
                        <span className="ic">{m.icon}</span>
                        <h3>{m.title}</h3>
                        <p>{m.blurb}</p>
                    </Link>
                ))}
            </div>
        </div>
    );
}

export function PortalPanel({ title, action, children }) {
    return (
        <div className="p-panel">
            {(title || action) && (
                <h3 style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span>{title}</span>
                    {action}
                </h3>
            )}
            {children}
        </div>
    );
}

export function PortalStat({ value, label }) {
    return (
        <div className="p-stat">
            <div className="v">{value}</div>
            <div className="l">{label}</div>
        </div>
    );
}

export function PortalFooter() {
    return (
        <div className="p-wrap">
            <div className="p-strip">
                🛡 15th Strike Wing • Philippine Air Force • {new Date().getFullYear()}
            </div>
        </div>
    );
}
