import{u as f,r as h,j as e,L as a,b as w}from"./app-C1DWYSLS.js";import{A as v}from"./ApplicationLogo-BPG85q6Q.js";const y=`
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
`;function k(o){return(o||"").replace(/,/g," ").split(/\s+/).filter(Boolean).slice(0,2).map(s=>s[0].toUpperCase()).join("")}function z({header:o,children:s}){const{user:t,isAdmin:n,isSuperadmin:g,canSwitchView:i,viewMode:l}=f().props.auth,d=()=>w.post(route("view-mode.toggle")),[c,u]=h.useState(!1),[x,p]=h.useState(!1),m=[{label:"Dashboard",href:route("dashboard"),on:route().current("dashboard")},!n&&{label:"My Profile",href:route("my-profile.edit"),on:route().current("my-profile.*")},{label:n?"Leave requests":"My Leave",href:route(n?"leave.requests":"leave.index"),on:route().current("leave.*")},{label:"IWOT",href:route("iwot.index"),on:route().current("iwot.*")},{label:"IPCR",href:route("ipcr.index"),on:route().current("ipcr.*")},n&&{label:"Personnel",href:route("admin.users.index"),on:route().current("admin.users.*")||route().current("admin.employees.*")},n&&{label:"Balances",href:route("admin.balances.index"),on:route().current("admin.balances.*")}].filter(Boolean),b=l==="employee"?"← Back to admin":"View as employee";return e.jsxs("div",{className:"min-h-screen bg-gray-100",children:[e.jsx("style",{children:y}),e.jsxs("nav",{className:"navbar-15sw",children:[e.jsxs("div",{className:"bar",children:[e.jsxs(a,{href:"/",className:"brand",children:[e.jsx(v,{compact:!0}),e.jsxs("span",{className:"wm",children:[e.jsx("b",{children:"15th Strike Wing"}),e.jsx("span",{children:"Civilian Personnel Management System"})]})]}),e.jsx("div",{className:"links",children:m.map(r=>e.jsx(a,{href:r.href,className:r.on?"on":"",children:r.label},r.label))}),e.jsxs("div",{className:"right",children:[i&&e.jsx("button",{className:"hat",onClick:d,title:"Admins can preview the app exactly as an employee sees it",children:b}),e.jsxs("button",{className:"chip",onClick:()=>p(r=>!r),children:[e.jsx("span",{className:"av",children:k(t.name)}),e.jsxs("span",{style:{lineHeight:1.15},children:[t.name,e.jsx("span",{className:"role",children:l==="employee"?"employee":g?"superadmin":n?"admin":"employee"})]}),e.jsx("span",{style:{color:"#8ba3bf",fontSize:".7rem"},children:"▾"})]})]}),e.jsx("button",{className:"burger",onClick:()=>u(r=>!r),children:c?"✕":"☰"})]}),x&&e.jsxs("div",{className:"menu",onMouseLeave:()=>p(!1),children:[e.jsx(a,{href:route("profile.edit"),children:"Account settings"}),e.jsx(a,{href:route("logout"),method:"post",as:"button",children:"Log Out"})]}),c&&e.jsxs("div",{className:"drawer",children:[m.map(r=>e.jsx(a,{href:r.href,className:r.on?"on":"",children:r.label},r.label)),e.jsx("div",{className:"sep"}),e.jsx("div",{className:"who",children:t.name}),i&&e.jsx("button",{onClick:d,children:b}),e.jsx(a,{href:route("profile.edit"),children:"Account settings"}),e.jsx(a,{href:route("logout"),method:"post",as:"button",children:"Log Out"})]})]}),i&&l==="employee"&&e.jsxs("div",{className:"hatbar-15sw",children:["You are in ",e.jsx("strong",{children:"employee mode"})," — filing and viewing your own records only. ",e.jsx("button",{onClick:d,children:"Back to admin"})]}),o&&e.jsx("header",{className:"pagehead-15sw",children:e.jsx("div",{className:"inner",children:o})}),e.jsx("main",{children:s})]})}export{z as A};
