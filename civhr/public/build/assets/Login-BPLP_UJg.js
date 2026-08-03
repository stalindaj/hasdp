import{a as g,j as e,H as c,L as m}from"./app-B0LTq1UB.js";function x({status:s,canResetPassword:t}){const{data:i,setData:n,post:o,processing:l,errors:r,reset:d}=g({login:"",password:"",remember:!1}),p=a=>{a.preventDefault(),o(route("login"),{onFinish:()=>d("password")})};return e.jsxs(e.Fragment,{children:[e.jsx(c,{title:"Sign in"}),e.jsx("style",{children:`
                .cpms-login { --navy:#0b2a52; --navy-dark:#071b35; --gold:#c9a341;
                    min-height:100vh; font-family:'Segoe UI',Arial,sans-serif; overflow:hidden; }
                .bg-military { position:fixed; inset:0; z-index:0;
                    background:radial-gradient(ellipse at center,#0a1628 0%,#02060d 100%); overflow:hidden; }
                .grid-bg { position:absolute; inset:0; pointer-events:none;
                    background-image:linear-gradient(rgba(0,255,120,.03) 1px,transparent 1px),
                        linear-gradient(90deg,rgba(0,255,120,.03) 1px,transparent 1px);
                    background-size:60px 60px; animation:gpulse 3s ease-in-out infinite; }
                @keyframes gpulse {0%,100%{opacity:.3}50%{opacity:.8}}
                .radar-line { position:absolute; top:0; left:-2px; width:4px; height:100%;
                    background:linear-gradient(180deg,transparent,rgba(0,255,120,.9) 50%,transparent);
                    box-shadow:0 0 20px rgba(0,255,120,.3); animation:sweep 4s ease-in-out infinite; }
                @keyframes sweep {0%{transform:translateX(-100%);opacity:0}5%{opacity:1}
                    45%,55%{transform:translateX(50vw);opacity:1}95%{opacity:1}100%{transform:translateX(100vw);opacity:0}}
                .scanlines { position:absolute; inset:0; pointer-events:none;
                    background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,255,120,.02) 3px,rgba(0,255,120,.02) 4px); }
                .blip { position:absolute; border-radius:50%; pointer-events:none;
                    background:rgba(0,255,120,.9); box-shadow:0 0 18px rgba(0,255,120,.5); animation:blip 4s ease-in-out infinite; }
                @keyframes blip {0%{opacity:0;transform:translateX(0) scale(0)}20%{opacity:1;transform:translateX(40vw) scale(1.1)}
                    60%{opacity:1;transform:translateX(75vw) scale(1)}100%{opacity:0;transform:translateX(110vw) scale(.4)}}
                .login-wrap { position:relative; z-index:1; min-height:100vh; display:flex;
                    align-items:center; justify-content:center; padding:20px; }
                .login-card { max-width:430px; width:100%; border-top:5px solid var(--gold); border-radius:10px;
                    background:rgba(11,42,82,.92); backdrop-filter:blur(15px); color:#fff; padding:2rem;
                    box-shadow:0 25px 60px rgba(0,0,0,.6),0 0 0 1px rgba(201,163,65,.15); }
                .brand-badge { width:72px; height:72px; border-radius:50%; background:var(--gold);
                    display:flex; align-items:center; justify-content:center; margin:0 auto 12px;
                    box-shadow:0 0 30px rgba(201,163,65,.3); overflow:hidden; }
                .brand-badge img { height:60px; width:60px; object-fit:contain; }
                .cpms-login h1 { font-size:1.05rem; font-weight:700; text-align:center; margin:0; }
                .cpms-login .sub { display:block; text-align:center; font-size:.72rem; color:rgba(255,255,255,.6); margin-bottom:1.25rem; }
                .cpms-login label { font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:rgba(255,255,255,.8); }
                .cpms-login input[type=text],.cpms-login input[type=password] { width:100%; margin-top:.3rem; padding:.6rem .75rem;
                    border-radius:6px; background:rgba(255,255,255,.08); border:1px solid rgba(201,163,65,.25); color:#fff; outline:none; }
                .cpms-login input:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(201,163,65,.15); background:rgba(255,255,255,.12); }
                .cpms-login .field { margin-bottom:1rem; }
                .cpms-login .err { color:#f8b7bd; font-size:.72rem; margin-top:.25rem; }
                .cpms-login .row { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; font-size:.8rem; }
                .cpms-login .row a { color:var(--gold); text-decoration:none; }
                .btn-gold { width:100%; padding:.65rem; border:0; border-radius:6px; font-weight:700;
                    background:var(--gold); color:var(--navy-dark); cursor:pointer; }
                .btn-gold:hover { background:#b8922f; } .btn-gold:disabled { opacity:.6; cursor:not-allowed; }
                .status-ok { background:rgba(40,167,69,.2); color:#c8f7d4; padding:.5rem .75rem; border-radius:6px; font-size:.8rem; margin-bottom:1rem; }
            `}),e.jsxs("div",{className:"cpms-login",children:[e.jsxs("div",{className:"bg-military",children:[e.jsx("div",{className:"grid-bg"}),e.jsx("div",{className:"scanlines"}),e.jsx("div",{className:"radar-line"}),e.jsx("div",{className:"blip",style:{top:"20%",left:"-10%",width:6,height:6,animationDelay:".4s"}}),e.jsx("div",{className:"blip",style:{top:"45%",left:"-10%",width:5,height:5,animationDelay:"1.6s"}}),e.jsx("div",{className:"blip",style:{top:"70%",left:"-10%",width:7,height:7,animationDelay:"2.6s"}})]}),e.jsx("div",{className:"login-wrap",children:e.jsxs("div",{className:"login-card",children:[e.jsx("div",{className:"brand-badge",children:e.jsx("img",{src:"/images/agency-logo.png",alt:"15th Strike Wing",onError:a=>a.currentTarget.style.display="none"})}),e.jsx("h1",{children:"15TH STRIKE WING"}),e.jsx("span",{className:"sub",children:"Civilian Personnel Management System"}),s&&e.jsx("div",{className:"status-ok",children:s}),e.jsxs("form",{onSubmit:p,children:[e.jsxs("div",{className:"field",children:[e.jsx("label",{htmlFor:"login",children:"Employee number or email"}),e.jsx("input",{id:"login",type:"text",name:"login",value:i.login,autoComplete:"username",autoFocus:!0,onChange:a=>n("login",a.target.value)}),r.login&&e.jsx("div",{className:"err",children:r.login})]}),e.jsxs("div",{className:"field",children:[e.jsx("label",{htmlFor:"password",children:"Password"}),e.jsx("input",{id:"password",type:"password",name:"password",value:i.password,autoComplete:"current-password",onChange:a=>n("password",a.target.value)}),r.password&&e.jsx("div",{className:"err",children:r.password})]}),e.jsxs("div",{className:"row",children:[e.jsxs("label",{style:{textTransform:"none",fontWeight:400,color:"rgba(255,255,255,.8)"},children:[e.jsx("input",{type:"checkbox",checked:i.remember,onChange:a=>n("remember",a.target.checked),style:{marginRight:6}}),"Remember me"]}),t&&e.jsx(m,{href:route("password.request"),children:"Forgot password?"})]}),e.jsx("button",{type:"submit",className:"btn-gold",disabled:l,children:"Sign in"})]})]})})]})]})}export{x as default};
