import{j as e,r as v,L as h,u as C,H as B,a as D,b as _}from"./app-C1DWYSLS.js";import{A as O}from"./AuthenticatedLayout-BD75KkVm.js";import{M as L}from"./Modal-B2ejqHLc.js";import{I as m}from"./InputLabel-C3fEqcFV.js";import{T as g}from"./TextInput-DN4Z0KVh.js";import{I as x}from"./InputError-CVNk2tlj.js";import{P}from"./PrimaryButton-R3Hdm9m9.js";import{S as z}from"./SecondaryButton-CAalDaAC.js";import"./ApplicationLogo-BPG85q6Q.js";import"./transition-BLo9LBJe.js";const H=`
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
`;function q(t){return(t||"").replace(/,/g," ").split(/\s+/).filter(Boolean).slice(0,2).map(r=>r[0].toUpperCase()).join("")}function $({children:t}){return e.jsxs("div",{className:"portal",children:[e.jsx("style",{children:H}),t]})}function A({clearance:t}){const[r,a]=v.useState(()=>new Date);return v.useEffect(()=>{const l=setInterval(()=>a(new Date),3e4);return()=>clearInterval(l)},[]),e.jsxs("div",{className:"p-ticker",children:[e.jsxs("span",{children:[e.jsx("span",{className:"dot"}),e.jsx("span",{className:"lbl",children:"STATUS"})," · SYSTEMS NOMINAL"]}),e.jsxs("span",{children:[e.jsx("span",{className:"lbl",children:"DATE"})," · ",I(r)]}),e.jsxs("span",{children:[e.jsx("span",{className:"lbl",children:"TIME"})," ·"," ",r.toLocaleTimeString("en-GB",{hour:"2-digit",minute:"2-digit",hour12:!1})," PHT"]}),t&&e.jsxs("span",{children:[e.jsx("span",{className:"lbl",children:"CLEARANCE"})," · ",t]})]})}const I=t=>t.toLocaleDateString("en-GB",{day:"2-digit",month:"short",year:"numeric"});function F({name:t,designation:r,subtitle:a,compact:l=!1,children:c}){return e.jsx("section",{className:`p-hero${l?" compact":""}`,children:e.jsxs("div",{className:`p-card${l?" wide":""}`,children:[e.jsx("i",{className:"c tl"}),e.jsx("i",{className:"c tr"}),e.jsx("i",{className:"c bl"}),e.jsx("i",{className:"c br"}),e.jsx("div",{className:"p-badge",children:"🛡 15th Strike Wing"}),e.jsxs("div",{className:`p-avatar-wrap${l?" sm":""}`,children:[e.jsx("div",{className:"p-radar"}),e.jsx("div",{className:"p-avatar",children:q(t)})]}),e.jsxs("h1",{className:`p-title${l?" sm":""}`,children:["Welcome, ",e.jsx("span",{children:t})]}),e.jsxs("p",{className:"p-sub",children:[e.jsx("b",{children:"»"})," ",a," ",e.jsx("b",{children:"«"})]}),e.jsxs("div",{className:"p-meta",children:[e.jsxs("div",{children:[e.jsx("span",{children:t}),e.jsx("span",{className:"k",children:"Personnel"})]}),r&&e.jsxs("div",{children:[e.jsx("span",{children:r}),e.jsx("span",{className:"k",children:"Designation"})]}),e.jsxs("div",{children:[e.jsx("span",{children:I(new Date)}),e.jsx("span",{className:"k",children:"Today"})]})]}),c]})})}function M({modules:t,label:r="Quick Access"}){return e.jsxs("div",{className:"p-wrap",style:{paddingTop:".5rem",paddingBottom:"1.5rem"},children:[e.jsx("div",{className:"p-label",children:r}),e.jsx("div",{className:"p-modules",children:t.map((a,l)=>e.jsxs(h,{href:a.href,className:"p-mod",children:[e.jsx("span",{className:"tag",children:`MOD // ${String(l+1).padStart(2,"0")}`}),e.jsx("span",{className:"go",children:"↗"}),e.jsx("span",{className:"ic",children:a.icon}),e.jsx("h3",{children:a.title}),e.jsx("p",{children:a.blurb})]},a.title))})]})}function d({title:t,action:r,children:a}){return e.jsxs("div",{className:"p-panel",children:[(t||r)&&e.jsxs("h3",{style:{display:"flex",justifyContent:"space-between",alignItems:"center"},children:[e.jsx("span",{children:t}),r]}),a]})}function p({value:t,label:r}){return e.jsxs("div",{className:"p-stat",children:[e.jsx("div",{className:"v",children:t}),e.jsx("div",{className:"l",children:r})]})}function T(){return e.jsx("div",{className:"p-wrap",children:e.jsxs("div",{className:"p-strip",children:["🛡 15th Strike Wing • Philippine Air Force • ",new Date().getFullYear()]})})}const G=t=>[{title:"Personnel",blurb:"Accounts & records",icon:"👥",href:()=>route("admin.users.index")},{title:"Leave requests",blurb:"Decide pending leave",icon:"🗓️",href:()=>route("leave.requests")},{title:"IWOT",blurb:"Work output targets",icon:"🎯",href:()=>route("iwot.index")},{title:"IPCR",blurb:"Performance review",icon:"📋",href:()=>route("ipcr.index")},{title:"Holidays",blurb:"Non-working days",icon:"📅",href:()=>route("admin.holidays.index")},t&&{title:"Audit trail",blurb:"Every act logged",icon:"🧾",href:()=>route("admin.audit.index")}].filter(Boolean),U=[{title:"IPCR",blurb:"Individual Performance",icon:"📋",href:()=>route("ipcr.index")},{title:"IWOT",blurb:"Work Output Targets",icon:"🎯",href:()=>route("iwot.index")},{title:"Leave",blurb:"File / View Leave",icon:"🗓️",href:()=>route("leave.index")}],E=t=>t.map(r=>({...r,href:r.href()}));function S({done:t,onClick:r}){return e.jsx("button",{type:"button",onClick:r,title:"Click to toggle",className:`p-chip${t?" done":""}`,children:t?"✓":"—"})}function V({status:t}){const r={pending:"bg-amber-50 text-amber-700 ring-amber-200",approved:"bg-emerald-50 text-emerald-700 ring-emerald-200",rejected:"bg-red-50 text-red-700 ring-red-200"};return e.jsx("span",{className:`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset ${r[t]??r.pending}`,children:t})}function R({entry:t,dark:r=!1}){if(!t.certificate&&!t.photo)return null;const a=r?"p-link":"font-medium text-blue-600 underline-offset-2 hover:underline";return e.jsxs("span",{className:"space-x-2 text-xs",children:[t.certificate&&e.jsx("a",{href:t.certificate,target:"_blank",rel:"noopener",className:a,children:"Certificate"}),t.photo&&e.jsx("a",{href:t.photo,target:"_blank",rel:"noopener",className:a,children:"Photo"})]})}function Y({year:t,ldTarget:r,rows:a,boxes:l,pendingLeaves:c,pendingLd:n}){const{user:N,isSuperadmin:i}=C().props.auth,u=N?.name??"Administrator",w=(s,j)=>{let y=null;j==="rejected"&&(y=prompt(`Reason for rejecting "${s.title}"?`),!y)||_.patch(route("ld.decide",s.id),{decision:j,remarks:y},{preserveScroll:!0})},[f,b]=v.useState(null),o=D({title:"",hours:"",date:new Date().toISOString().slice(0,10)}),k=(s,j)=>_.patch(route("dashboard.ipcr",s.id),{sem:j},{preserveScroll:!0}),W=s=>{s.preventDefault(),o.post(route("dashboard.ld",f.id),{preserveScroll:!0,onSuccess:()=>{o.reset(),b(null)}})};return e.jsxs($,{children:[e.jsx(A,{clearance:"admin"}),e.jsx(F,{compact:!0,name:u,designation:"Administrator",subtitle:"Command view · personnel status"}),e.jsxs("div",{className:"p-wrap",children:[e.jsx("div",{className:"p-label",children:"Personnel Status Board"}),e.jsxs("div",{className:"grid gap-4 sm:grid-cols-3",children:[e.jsx(d,{title:`IPCR status · ${t}`,children:e.jsxs("div",{className:"p-stats",children:[e.jsx(p,{value:`${l.ipcr.sem1_done}/${l.ipcr.total}`,label:"1st sem submitted"}),e.jsx(p,{value:`${l.ipcr.sem2_done}/${l.ipcr.total}`,label:"2nd sem submitted"})]})}),e.jsx(d,{title:"Leave",children:e.jsxs("div",{className:"p-stats",children:[e.jsx(p,{value:l.leave.pending,label:"pending approval"}),e.jsx(p,{value:`${l.leave.used_days}d`,label:`days used · ${t}`})]})}),e.jsx(d,{title:"Learning & Development",children:e.jsxs("div",{className:"p-stats",children:[e.jsx(p,{value:`${l.ld.total_hours}h`,label:`hours approved · ${t}`}),e.jsx(p,{value:l.ld.behind,label:`below ${r}h target`}),e.jsx(p,{value:l.ld.pending,label:"awaiting approval"})]})})]}),e.jsxs("div",{className:"mt-4 grid gap-4 lg:grid-cols-[1fr_320px]",children:[e.jsx(d,{title:`Roster · ${a.length} personnel`,children:e.jsx("div",{className:"overflow-x-auto",children:e.jsxs("table",{className:"p-table",children:[e.jsx("thead",{children:e.jsxs("tr",{children:[e.jsx("th",{children:"Employee"}),e.jsx("th",{style:{textAlign:"center"},children:"IPCR 1st"}),e.jsx("th",{style:{textAlign:"center"},children:"IPCR 2nd"}),e.jsx("th",{children:"Leave"}),e.jsx("th",{children:"L&D"}),e.jsx("th",{})]})}),e.jsx("tbody",{children:a.map(s=>e.jsxs("tr",{children:[e.jsxs("td",{children:[e.jsx(h,{href:route("dashboard.employee",s.id),children:s.name}),e.jsxs("div",{className:"dim",children:["#",s.emp_no]})]}),e.jsx("td",{style:{textAlign:"center"},children:e.jsx(S,{done:s.sem1,onClick:()=>k(s,1)})}),e.jsx("td",{style:{textAlign:"center"},children:e.jsx(S,{done:s.sem2,onClick:()=>k(s,2)})}),e.jsxs("td",{children:[e.jsxs(h,{href:route("dashboard.employee",s.id),title:"View balances & ledger",children:[s.leave_used,"d used"]}),s.leave_pending>0&&e.jsxs("div",{className:"warn",children:[s.leave_pending," pending"]})]}),e.jsxs("td",{children:[e.jsxs("div",{children:[s.ld_hours,"h"]}),s.ld_pending>0?e.jsxs("div",{className:"dim",children:[s.ld_pending,"h pending"]}):e.jsx("div",{className:"ok",children:"target met"})]}),e.jsx("td",{style:{textAlign:"right"},children:e.jsx("button",{className:"p-link",onClick:()=>b(s),children:"+ L&D"})})]},s.id))})]})})}),e.jsxs("aside",{className:"space-y-4",children:[e.jsx(d,{title:"Pending L&D",children:n.length===0?e.jsx("p",{className:"p-empty",children:"Nothing waiting."}):e.jsx("ul",{className:"space-y-2",children:n.map(s=>e.jsxs("li",{className:"p-item",children:[e.jsx("p",{className:"text-sm font-medium",children:s.employee}),e.jsxs("p",{className:"dim text-xs",children:[s.title," · ",s.hours,"h · ",s.date]}),e.jsxs("div",{className:"mt-1.5 flex items-center justify-between",children:[e.jsx(R,{entry:s,dark:!0}),e.jsxs("span",{className:"space-x-3",children:[e.jsx("button",{className:"p-link",style:{color:"#3ddc84"},onClick:()=>w(s,"approved"),children:"Approve"}),e.jsx("button",{className:"p-link",style:{color:"#f08a80"},onClick:()=>w(s,"rejected"),children:"Reject"})]})]})]},s.id))})}),e.jsx(d,{title:"Pending leave",action:e.jsx(h,{href:route("leave.requests"),className:"p-link",children:"View all"}),children:c.length===0?e.jsx("p",{className:"p-empty",children:"Nothing waiting."}):e.jsx("ul",{className:"space-y-2",children:c.map(s=>e.jsx("li",{children:e.jsxs(h,{href:route("leave.show",s.id),className:"p-item",children:[e.jsx("p",{className:"text-sm font-medium",children:s.applicant}),e.jsxs("p",{className:"dim text-xs",children:[s.type," · ",s.days,"d · ",s.inclusive]})]})},s.id))})})]})]})]}),e.jsx(M,{modules:E(G(i))}),e.jsx(T,{}),e.jsx(L,{show:!!f,onClose:()=>b(null),maxWidth:"md",children:f&&e.jsxs("form",{onSubmit:W,className:"p-6",children:[e.jsxs("h3",{className:"text-lg font-semibold text-slate-800",children:["Log L&D — ",f.name]}),e.jsxs("div",{className:"mt-5 space-y-4",children:[e.jsxs("div",{children:[e.jsx(m,{htmlFor:"ld_title",value:"Training title"}),e.jsx(g,{id:"ld_title",className:"mt-1 block w-full",value:o.data.title,onChange:s=>o.setData("title",s.target.value)}),e.jsx(x,{message:o.errors.title,className:"mt-1"})]}),e.jsxs("div",{className:"grid grid-cols-2 gap-4",children:[e.jsxs("div",{children:[e.jsx(m,{htmlFor:"ld_hours",value:"Hours"}),e.jsx(g,{id:"ld_hours",type:"number",step:"0.5",min:"0.5",className:"mt-1 block w-full",value:o.data.hours,onChange:s=>o.setData("hours",s.target.value)}),e.jsx(x,{message:o.errors.hours,className:"mt-1"})]}),e.jsxs("div",{children:[e.jsx(m,{htmlFor:"ld_date",value:"Date"}),e.jsx(g,{id:"ld_date",type:"date",className:"mt-1 block w-full",value:o.data.date,onChange:s=>o.setData("date",s.target.value)})]})]})]}),e.jsxs("div",{className:"mt-6 flex justify-end gap-3",children:[e.jsx(z,{type:"button",onClick:()=>b(null),children:"Cancel"}),e.jsx(P,{disabled:o.processing,children:"Log training"})]})]})})]})}function J({year:t,ldTarget:r,me:a}){const[l,c]=v.useState(!1),n=D({title:"",hours:"",date:"",certificate:null,photo:null}),N=i=>{i.preventDefault(),n.post(route("ld.store"),{forceFormData:!0,preserveScroll:!0,onSuccess:()=>{n.reset(),c(!1)}})};return e.jsxs($,{children:[e.jsx(A,{clearance:a.clearance}),e.jsx(F,{name:a.name,designation:a.position,subtitle:"Ready to serve. Ready to strike."}),e.jsx(M,{modules:E(U)}),e.jsxs("div",{className:"p-wrap",children:[e.jsx("div",{className:"p-label",children:"My Status"}),e.jsxs("div",{className:"grid gap-4 sm:grid-cols-3",children:[e.jsx(d,{title:`My IPCR · ${t}`,children:e.jsxs("div",{className:"space-y-2 text-sm",children:[e.jsxs("p",{className:"flex items-center justify-between",children:[e.jsx("span",{children:"1st semester"}),e.jsx("span",{className:`p-chip${a.sem1?" done":""}`,children:a.sem1?"✓":"—"})]}),e.jsxs("p",{className:"flex items-center justify-between",children:[e.jsx("span",{children:"2nd semester"}),e.jsx("span",{className:`p-chip${a.sem2?" done":""}`,children:a.sem2?"✓":"—"})]})]})}),e.jsxs(d,{title:"My leave balances",children:[a.balances?e.jsx("div",{className:"grid grid-cols-2 gap-x-4 gap-y-1 text-sm",children:[["VL",a.balances.vl],["SL",a.balances.sl],["Wellness",a.balances.wellness],["SPL",a.balances.spl]].map(([i,u])=>e.jsxs("p",{className:"flex justify-between",children:[e.jsx("span",{className:"dim",children:i}),e.jsx("span",{className:"font-semibold",children:u})]},i))}):e.jsx("p",{className:"p-empty",children:"No employee record linked."}),a.leave_pending>0&&e.jsxs("p",{className:"warn mt-2",children:[a.leave_pending," application(s) pending"]}),e.jsx(h,{href:route("leave.create"),className:"p-link mt-3 inline-block",children:"File leave →"})]}),e.jsxs(d,{title:"My L&D",children:[e.jsx(p,{value:`${a.ld_hours}h`,label:`approved, of ${r}h · ${t}`}),a.ld_pending>0?e.jsxs("p",{className:"dim mt-1 text-xs",children:[a.ld_pending,"h still pending"]}):e.jsx("p",{className:"ok mt-1 text-xs",children:"target met"}),e.jsx("button",{type:"button",onClick:()=>c(!0),className:"p-link mt-3 inline-block",children:"Submit a training →"})]})]}),a.ld_entries.length>0&&e.jsx("div",{className:"mt-4",children:e.jsx(d,{title:`My trainings · ${t}`,children:e.jsx("ul",{className:"space-y-2 text-sm",children:a.ld_entries.map((i,u)=>e.jsxs("li",{className:"p-item",children:[e.jsxs("div",{className:"flex items-center justify-between gap-3",children:[e.jsx("span",{children:i.title}),e.jsxs("span",{className:"dim flex shrink-0 items-center gap-2",children:[i.hours,"h · ",i.date,e.jsx(V,{status:i.status})]})]}),e.jsxs("div",{className:"mt-0.5 flex items-center justify-between",children:[e.jsx(R,{entry:i,dark:!0}),i.status==="rejected"&&i.remarks&&e.jsx("p",{className:"text-xs",style:{color:"#f08a80"},children:i.remarks})]})]},u))})})})]}),e.jsx(T,{}),e.jsx(L,{show:l,onClose:()=>c(!1),maxWidth:"md",children:e.jsxs("form",{onSubmit:N,className:"p-6",children:[e.jsx("h3",{className:"text-lg font-semibold text-slate-800",children:"Submit a training"}),e.jsx("p",{className:"mt-1 text-sm text-slate-500",children:"Attach the certificate, a photo taken during the training, or both. The hours count once an admin approves."}),e.jsxs("div",{className:"mt-5 space-y-4",children:[e.jsxs("div",{children:[e.jsx(m,{htmlFor:"my_ld_title",value:"Training title"}),e.jsx(g,{id:"my_ld_title",className:"mt-1 block w-full",value:n.data.title,onChange:i=>n.setData("title",i.target.value)}),e.jsx(x,{message:n.errors.title,className:"mt-1"})]}),e.jsxs("div",{className:"grid grid-cols-2 gap-4",children:[e.jsxs("div",{children:[e.jsx(m,{htmlFor:"my_ld_hours",value:"Hours"}),e.jsx(g,{id:"my_ld_hours",type:"number",step:"0.5",min:"0.5",className:"mt-1 block w-full",value:n.data.hours,onChange:i=>n.setData("hours",i.target.value)}),e.jsx(x,{message:n.errors.hours,className:"mt-1"})]}),e.jsxs("div",{children:[e.jsx(m,{htmlFor:"my_ld_date",value:"Date"}),e.jsx(g,{id:"my_ld_date",type:"date",className:"mt-1 block w-full",value:n.data.date,onChange:i=>n.setData("date",i.target.value)}),e.jsx(x,{message:n.errors.date,className:"mt-1"})]})]}),e.jsxs("div",{children:[e.jsx(m,{htmlFor:"my_ld_cert",value:"Certificate (photo/scan)"}),e.jsx("input",{id:"my_ld_cert",type:"file",accept:"image/*",className:"mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100",onChange:i=>n.setData("certificate",i.target.files[0]??null)}),e.jsx(x,{message:n.errors.certificate,className:"mt-1"})]}),e.jsxs("div",{children:[e.jsx(m,{htmlFor:"my_ld_photo",value:"Photo during the training"}),e.jsx("input",{id:"my_ld_photo",type:"file",accept:"image/*",className:"mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100",onChange:i=>n.setData("photo",i.target.files[0]??null)}),e.jsx(x,{message:n.errors.photo,className:"mt-1"})]}),e.jsx("p",{className:"text-xs text-slate-400",children:"At least one image is required · JPG/PNG · max 5 MB each."})]}),e.jsxs("div",{className:"mt-6 flex justify-end gap-3",children:[e.jsx(z,{type:"button",onClick:()=>c(!1),children:"Cancel"}),e.jsx(P,{disabled:n.processing,children:n.processing?"Submitting…":"Submit for approval"})]})]})})]})}function le(t){const a=C().props.flash;return e.jsxs(O,{children:[e.jsx(B,{title:"Dashboard"}),a?.success&&e.jsx("div",{className:"bg-emerald-500/15 px-4 py-2 text-center text-sm text-emerald-100",children:a.success}),t.mode==="admin"?e.jsx(Y,{...t}):e.jsx(J,{...t})]})}export{le as default};
