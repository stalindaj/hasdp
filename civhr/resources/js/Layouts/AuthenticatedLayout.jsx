import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, router, usePage } from '@inertiajs/react';

/**
 * The 15SW personnel-portal chrome: the dark navy/gold bar from the
 * standalone portal, worn by every page.
 *
 * Page CONTENT is free to be light — the leave / IPCR / IWOT sheets stay on
 * white because they are data entry and they print. Only the frame is dark.
 */

const NAV_CSS = `
.navbar-15sw {
    --navy-deep:#050d18; --navy:#0a1a2f; --gold:#f0c94e; --gold-dim:#c9a030;
    --steel:#8ba3bf; --steel-dim:#5c7995;
    --mono: ui-monospace, 'Cascadia Mono', 'Segoe UI Mono', Consolas, monospace;
    background:rgba(10,26,47,.96); border-bottom:2px solid var(--gold);
    box-shadow:0 4px 30px rgba(0,0,0,.5); position:relative; z-index:30;
}
.navbar-15sw a { text-decoration:none; }
.navbar-15sw .bar { display:flex; align-items:center; gap:1rem; padding:.55rem 1.25rem; max-width:1400px; margin:0 auto; }

.navbar-15sw .brand { display:flex; align-items:center; gap:.65rem; flex-shrink:0; }
.navbar-15sw .brand .wm { line-height:1.15; }
.navbar-15sw .brand .wm b {
    display:block; font-size:1.15rem; font-weight:700; letter-spacing:1.5px;
    text-transform:uppercase; color:var(--gold); text-shadow:0 0 12px rgba(240,201,78,.3);
}
.navbar-15sw .brand .wm span {
    display:block; font-family:var(--mono); font-size:.58rem; letter-spacing:2px;
    text-transform:uppercase; color:var(--steel-dim);
}
/* On a phone the seal alone carries the brand — the wordmark would push the
   menu button off the screen. */
@media (max-width:479px) {
    .navbar-15sw .brand .wm { display:none; }
    .navbar-15sw .bar { padding:.5rem .75rem; }
}
@media (min-width:480px) and (max-width:1023px) {
    .navbar-15sw .brand .wm b { font-size:.95rem; letter-spacing:1px; }
    .navbar-15sw .brand .wm span { font-size:.5rem; letter-spacing:1px; }
}

.navbar-15sw .links { display:none; gap:.15rem; flex:1; }
@media (min-width:1024px) { .navbar-15sw .links { display:flex; } }
.navbar-15sw .links a {
    position:relative; color:rgba(255,255,255,.75); font-weight:600; font-size:.86rem;
    padding:.5rem .85rem; border-radius:6px; transition:all .2s ease; white-space:nowrap;
}
.navbar-15sw .links a:hover, .navbar-15sw .links a.on { color:#fff; background:rgba(240,201,78,.15); }
.navbar-15sw .links a.on::after {
    content:''; position:absolute; bottom:2px; left:50%; transform:translateX(-50%);
    width:60%; height:2px; background:var(--gold); border-radius:4px;
}

.navbar-15sw .right { display:none; align-items:center; gap:.6rem; margin-left:auto; }
@media (min-width:1024px) { .navbar-15sw .right { display:flex; } }

.navbar-15sw .hat {
    font-family:var(--mono); font-size:.66rem; letter-spacing:1px; text-transform:uppercase;
    padding:.35rem .8rem; border-radius:999px; cursor:pointer;
    background:transparent; border:1px solid rgba(240,201,78,.45); color:var(--gold);
    transition:all .2s ease; white-space:nowrap;
}
.navbar-15sw .hat:hover { background:rgba(240,201,78,.15); }

.navbar-15sw .chip {
    display:flex; align-items:center; gap:9px; background:rgba(255,255,255,.07);
    border:1.5px solid rgba(240,201,78,.3); border-radius:8px; padding:3px 12px 3px 3px;
    color:#fff; font-weight:600; font-size:.85rem; cursor:pointer; transition:border-color .2s;
}
.navbar-15sw .chip:hover { border-color:var(--gold); }
.navbar-15sw .chip .av {
    width:30px; height:30px; border-radius:6px; display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#16324f,#0a1a2f); border:1px solid rgba(240,201,78,.35);
    font-size:.72rem; font-weight:700; color:var(--gold);
}
.navbar-15sw .chip .role {
    display:block; font-family:var(--mono); font-size:.55rem; letter-spacing:1.5px;
    text-transform:uppercase; color:var(--gold-dim); font-weight:600;
}
.navbar-15sw .menu {
    position:absolute; right:1.25rem; top:100%; margin-top:.4rem; min-width:190px; z-index:40;
    background:#0a1a2f; border:1px solid rgba(240,201,78,.25); border-radius:8px;
    box-shadow:0 18px 50px rgba(0,0,0,.6); overflow:hidden;
}
.navbar-15sw .menu a, .navbar-15sw .menu button {
    display:block; width:100%; text-align:left; padding:.6rem .9rem; font-size:.84rem;
    color:rgba(255,255,255,.85); background:none; border:0; cursor:pointer;
}
.navbar-15sw .menu a:hover, .navbar-15sw .menu button:hover { background:rgba(240,201,78,.12); color:#fff; }

.navbar-15sw .burger {
    margin-left:auto; background:none; border:1px solid rgba(240,201,78,.35); color:var(--gold);
    border-radius:6px; padding:.35rem .6rem; cursor:pointer; font-size:1.1rem; line-height:1;
}
@media (min-width:1024px) { .navbar-15sw .burger { display:none; } }
.navbar-15sw .drawer { border-top:1px solid rgba(240,201,78,.2); padding:.5rem .75rem 1rem; }
.navbar-15sw .drawer a, .navbar-15sw .drawer button {
    display:block; width:100%; text-align:left; padding:.55rem .75rem; border-radius:6px;
    color:rgba(255,255,255,.82); font-size:.9rem; font-weight:600; background:none; border:0; cursor:pointer;
}
.navbar-15sw .drawer a.on { background:rgba(240,201,78,.15); color:#fff; }
.navbar-15sw .drawer .sep { border-top:1px solid rgba(255,255,255,.08); margin:.5rem 0; }
.navbar-15sw .drawer .who { padding:.4rem .75rem; color:var(--steel-dim); font-size:.75rem; }

/* Which hat is on — never a guess. */
.hatbar-15sw {
    background:rgba(240,201,78,.12); border-bottom:1px solid rgba(240,201,78,.3);
    color:#f6e2a8; text-align:center; padding:.45rem 1rem; font-size:.82rem;
}
.hatbar-15sw button { color:var(--gold,#f0c94e); font-weight:700; text-decoration:underline; background:none; border:0; cursor:pointer; }

/* Page title strip. Stays white: the pages that use it (leave, IPCR, IWOT)
   are white, and their buttons are styled for a light background. */
.pagehead-15sw { background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.1); }
.pagehead-15sw .inner { max-width:1400px; margin:0 auto; padding:1.25rem; }
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

export default function AuthenticatedLayout({ header, children }) {
    const { user, isAdmin, isSuperadmin, canSwitchView, viewMode } = usePage().props.auth;

    const switchView = () => router.post(route('view-mode.toggle'));
    const [drawer, setDrawer] = useState(false);
    const [menu, setMenu] = useState(false);

    // One list, rendered twice (bar + drawer) so they can never drift.
    const links = [
        { label: 'Dashboard', href: route('dashboard'), on: route().current('dashboard') },
        !isAdmin && {
            label: 'My Profile',
            href: route('my-profile.edit'),
            on: route().current('my-profile.*'),
        },
        {
            // Admins have no "my leave" — Leave is the requests queue until
            // they switch hats.
            label: isAdmin ? 'Leave requests' : 'My Leave',
            href: route(isAdmin ? 'leave.requests' : 'leave.index'),
            on: route().current('leave.*'),
        },
        { label: 'IWOT', href: route('iwot.index'), on: route().current('iwot.*') },
        { label: 'IPCR', href: route('ipcr.index'), on: route().current('ipcr.*') },
        isAdmin && {
            // Users + Employees live behind one "Personnel" tab.
            label: 'Personnel',
            href: route('admin.users.index'),
            on: route().current('admin.users.*') || route().current('admin.employees.*'),
        },
        isAdmin && {
            label: 'Balances',
            href: route('admin.balances.index'),
            on: route().current('admin.balances.*'),
        },
    ].filter(Boolean);

    const hatLabel = viewMode === 'employee' ? '← Back to admin' : 'View as employee';

    return (
        <div className="min-h-screen bg-gray-100">
            <style>{NAV_CSS}</style>

            <nav className="navbar-15sw">
                <div className="bar">
                    <Link href="/" className="brand">
                        <ApplicationLogo compact />
                        <span className="wm">
                            <b>15th Strike Wing</b>
                            <span>Civilian Personnel Management System</span>
                        </span>
                    </Link>

                    <div className="links">
                        {links.map((l) => (
                            <Link key={l.label} href={l.href} className={l.on ? 'on' : ''}>
                                {l.label}
                            </Link>
                        ))}
                    </div>

                    <div className="right">
                        {canSwitchView && (
                            <button
                                className="hat"
                                onClick={switchView}
                                title="Admins can preview the app exactly as an employee sees it"
                            >
                                {hatLabel}
                            </button>
                        )}
                        <button className="chip" onClick={() => setMenu((v) => !v)}>
                            <span className="av">{initialsOf(user.name)}</span>
                            <span style={{ lineHeight: 1.15 }}>
                                {user.name}
                                <span className="role">
                                    {viewMode === 'employee'
                                        ? 'employee'
                                        : isSuperadmin
                                          ? 'superadmin'
                                          : isAdmin
                                            ? 'admin'
                                            : 'employee'}
                                </span>
                            </span>
                            <span style={{ color: '#8ba3bf', fontSize: '.7rem' }}>▾</span>
                        </button>
                    </div>

                    <button className="burger" onClick={() => setDrawer((v) => !v)}>
                        {drawer ? '✕' : '☰'}
                    </button>
                </div>

                {menu && (
                    <div className="menu" onMouseLeave={() => setMenu(false)}>
                        <Link href={route('profile.edit')}>Account settings</Link>
                        <Link href={route('logout')} method="post" as="button">
                            Log Out
                        </Link>
                    </div>
                )}

                {drawer && (
                    <div className="drawer">
                        {links.map((l) => (
                            <Link key={l.label} href={l.href} className={l.on ? 'on' : ''}>
                                {l.label}
                            </Link>
                        ))}
                        <div className="sep" />
                        <div className="who">{user.name}</div>
                        {canSwitchView && (
                            <button onClick={switchView}>{hatLabel}</button>
                        )}
                        <Link href={route('profile.edit')}>Account settings</Link>
                        <Link href={route('logout')} method="post" as="button">
                            Log Out
                        </Link>
                    </div>
                )}
            </nav>

            {canSwitchView && viewMode === 'employee' && (
                <div className="hatbar-15sw">
                    You are in <strong>employee mode</strong> — filing and viewing your own records
                    only. <button onClick={switchView}>Back to admin</button>
                </div>
            )}

            {header && (
                <header className="pagehead-15sw">
                    <div className="inner">{header}</div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}
