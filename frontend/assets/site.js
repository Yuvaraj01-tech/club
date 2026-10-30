const API='../backend/api';

const $=(s,r=document)=>r.querySelector(s),
      $$=(s,r=document)=>[...r.querySelectorAll(s)];

const esc=s=>String(s??'').replace(
    /[&<>"']/g,
    m=>({
        '&':'&amp;',
        '<':'&lt;',
        '>':'&gt;',
        '"':'&quot;',
        "'":'&#039;'
    }[m])
);

const money=n=>'₹'+Number(n||0).toLocaleString(
    'en-IN',
    {
        minimumFractionDigits:0,
        maximumFractionDigits:2
    }
);

const fmtDate=d=>d
    ?new Date(d+'T00:00:00').toLocaleDateString(
        'en-IN',
        {
            day:'2-digit',
            month:'short',
            year:'numeric'
        }
    )
    :'—';

window.api=async(path,opt={})=>{
    const o={
        credentials:'include',
        ...opt
    };

    if(
        o.body &&
        !(o.body instanceof FormData)
    ){
        o.headers={
            'Content-Type':'application/json',
            ...(o.headers||{})
        };

        o.body=JSON.stringify(o.body);
    }

    const r=await fetch(`${API}/${path}`,o);

    let j={};

    try{
        j=await r.json();
    }catch{}

    if(
        !r.ok ||
        j.ok===false
    ){
        throw new Error(
            j.error ||
            `Request failed (${r.status})`
        );
    }

    return j;
};

function toast(msg){
    let t=$('.toast');

    if(!t){
        t=document.createElement('div');
        t.className='toast';
        document.body.appendChild(t);
    }

    t.textContent=msg;
    t.classList.add('show');

    clearTimeout(window.__tt);

    window.__tt=setTimeout(
        ()=>t.classList.remove('show'),
        2800
    );
}

function theme(){
    const saved=
        localStorage.getItem('hh_theme') ||
        'dark';

    document.documentElement.dataset.theme=saved;

    $$('.theme-btn').forEach(
        b=>b.onclick=()=>{
            const n=
                document.documentElement.dataset.theme==='dark'
                ?'light'
                :'dark';

            localStorage.setItem(
                'hh_theme',
                n
            );

            document.documentElement.dataset.theme=n;
        }
    );
}

async function currentUser(){
    try{
        const r=await api('auth/me.php');

        return {
            ...r.user,
            profile:r.profile,
            verification:r.verification
        };
    }catch{
        return null;
    }
}

async function buildNav(){

    const user=await currentUser();

    const box=$('.nav-actions');

    if(!box)return;

    const themeBtn=
        '<button class="theme-btn" aria-label="Theme">◐</button>';

    const displayName=(
        user?.profile?.full_name ||
        user?.email ||
        'Profile'
    )
    .trim()
    .split(/\s+/)[0] || 'Profile';

    const menu=`
        <div class="profile-wrap">
            <button
                class="profile-btn profile-name-btn"
                id="profileBtn"
                aria-expanded="false"
            >
                <span>${esc(displayName)}</span>
                <span class="profile-chevron">⌄</span>
            </button>

            <div
                class="profile-menu"
                id="profileMenu"
            >
                <a href="profile.html">My Profile</a>
                <a href="bookings.html">My Booking</a>
                <button id="logoutBtn">Logout</button>
            </div>
        </div>
    `;

    box.innerHTML=`
        <button
            class="icon-btn mobile-menu"
            id="mobileMenu"
        >
            ☰
        </button>

        ${themeBtn}

        ${
            user
            ?menu
            :`
                <span class="desktop-auth">
                    <a
                        class="btn secondary small"
                        href="signin.html"
                    >
                        Sign In
                    </a>
                </span>

                <a
                    class="btn small desktop-auth"
                    href="signup.html"
                >
                    Sign Up
                </a>
            `
        }
    `;

    $('#mobileMenu')?.addEventListener(
        'click',
        ()=>$('.nav-links')?.classList.toggle('show')
    );

    $('#profileBtn')?.addEventListener(
        'click',
        ()=>{
            const m=$('#profileMenu');
            const b=$('#profileBtn');

            m.classList.toggle('open');

            b.setAttribute(
                'aria-expanded',
                m.classList.contains('open')
                    ?'true'
                    :'false'
            );
        }
    );

    document.addEventListener(
        'click',
        e=>{
            const w=$('.profile-wrap');

            if(
                w &&
                !w.contains(e.target)
            ){
                $('#profileMenu')?.classList.remove('open');

                $('#profileBtn')?.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }
        }
    );

    $('#logoutBtn')?.addEventListener(
        'click',
        async()=>{
            await api(
                'auth/logout.php',
                {
                    method:'POST'
                }
            ).catch(()=>{});

            location.href='index.html';
        }
    );

    $$('.nav-links a').forEach(
        a=>{
            if(
                a.getAttribute('href')===
                location.pathname.split('/').pop()
            ){
                a.classList.add('active');
            }
        }
    );

    theme();
}

function ageFromDob(d){

    if(!d)return '';

    const dob=
        new Date(d+'T00:00:00');

    const now=new Date();

    let age=
        now.getFullYear() -
        dob.getFullYear();

    const m=
        now.getMonth() -
        dob.getMonth();

    if(
        m<0 ||
        (
            m===0 &&
            now.getDate()<dob.getDate()
        )
    ){
        age--;
    }

    return age;
}

function wireAge(){

    const d=$('#dob');
    const a=$('#age');

    if(d&&a){

        const f=()=>{
            a.value=ageFromDob(d.value);
        };

        d.addEventListener(
            'change',
            f
        );

        f();
    }
}

function statusHtml(s){

    const map={
        approved:['success','Approved'],
        pending:['warning','Pending'],
        pending_review:[
            'warning',
            'Pending Review'
        ],
        pending_profile:[
            'warning',
            'Profile Required'
        ],
        rejected:[
            'danger',
            'Correction Required'
        ],
        blocked:[
            'danger',
            'Blocked'
        ]
    };

    const x=
        map[s] ||
        ['','Unknown'];

    return `
        <span class="pill ${x[0]}">
            ${x[1]}
        </span>
    `;
}

function imgPath(p){
    return p
        ?`../backend/${p}`
        :'';
}

function eventCard(e){

    const image=
        imgPath(e.cover_image_path);

    return `
        <article class="card event-card">

            <a href="event-details.html?id=${e.id}">

                ${
                    image
                    ?`
                        <img
                            class="event-image"
                            src="${image}"
                            alt="${esc(e.event_name)}"
                        >
                    `
                    :`
                        <div class="event-image"></div>
                    `
                }

                <div class="event-body">

                    <div class="event-meta">
                        <span>
                            ${fmtDate(e.event_date)}
                        </span>

                        <span>•</span>

                        <span>
                            ${esc(e.venue)}
                        </span>
                    </div>

                    <h3>
                        ${esc(e.title)}
                    </h3>

                    <p class="muted">
                        ${esc(
                            e.description ||
                            'A curated Hello Hobnobbers event experience.'
                        )}
                    </p>

                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            gap:10px
                        "
                    >
                        <span class="price">
                            ${money(e.ticket_price)}
                        </span>

                        <span class="btn small">
                            View Event
                        </span>
                    </div>

                </div>

            </a>
            

        </article>
    `;
}

async function loadHome(){

    const box=$('#homeEvents');

    if(!box)return;

    try{

        const r=
            await api('events/list.php');

        box.innerHTML=
            r.events.length
            ?r.events
                .slice(0,6)
                .map(eventCard)
                .join('')
            :`
                <div
                    class="notice"
                    style="grid-column:1/-1"
                >
                    No upcoming events have been published yet.
                </div>
            `;
            generatePassQRCodes();


    }catch(e){

        box.innerHTML=
            '<div class="notice danger">Unable to load events.</div>';
    }
}

async function loadEvents(){

    const box=$('#eventsGrid');

    if(!box)return;

    try{

        const r=
            await api('events/list.php');

        box.innerHTML=
            r.events.length
            ?r.events
                .map(eventCard)
                .join('')
            :`
                <div
                    class="notice"
                    style="grid-column:1/-1"
                >
                    No upcoming events are available.
                </div>
            `;

    }catch(e){

        box.innerHTML=
            '<div class="notice danger">Unable to load events.</div>';
    }
}

async function loadEventDetails(){

    const id=
        new URLSearchParams(
            location.search
        ).get('id');

    if(!id)return;

    try{

        const r=
            await api(
                `events/details.php?id=${encodeURIComponent(id)}`
            );

        const e=r.event;

        $('#detail').innerHTML=`

            <div class="detail-grid">

                <div class="detail-cover">

                    ${
                        e.cover_image_path
                        ?`
                            <img
                                src="${imgPath(e.cover_image_path)}"
                                alt="${esc(e.title)}"
                            >
                        `
                        :`
                            <div
                                style="
                                    height:100%;
                                    display:grid;
                                    place-items:center;
                                    color:var(--gold);
                                    font-size:4rem
                                "
                            >
                                ✦
                            </div>
                        `
                    }

                </div>

                <div class="card detail-panel">

                    <div class="kicker">
                        ${esc(e.category||'Event')}
                    </div>

                    <h1>
                        ${esc(e.title)}
                    </h1>

                    <div class="event-meta">

                        <span>
                            📅 ${fmtDate(e.event_date)}
                        </span>

                        <span>
    🕘
    ${esc(e.start_time.slice(0,5))}
    –
    ${esc(e.end_time.slice(0,5))}
</span>

                        <span>
                            📍 ${esc(e.venue)}
                        </span>

                    </div>

                    <p class="muted">
                        ${esc(e.description||'')}
                    </p>

                    <div class="notice">

                        <b>Eligibility</b>

                        <br>

                        ${esc(
                            e.eligibility_notes ||
                            'Valid profile and event rules apply.'
                        )}

                    </div>

                    <h3>
                        Select entry
                    </h3>

                    <div class="slot-list">

                        ${r.slots.map(
                            (s,i)=>`
                                <button
                                    class="slot ${i===0?'selected':''}"
                                    data-slot="${s.id}"
                                >
                                    <span>
                                        ${esc(s.slot_name)}
                                    </span>

                                    <span>
                                        ${esc(
                                            s.start_time.slice(0,5)
                                        )}
                                        –
                                        ${esc(
                                            s.end_time.slice(0,5)
                                        )}
                                    </span>
                                </button>
                            `
                        ).join('')}

                    </div>

                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            margin:20px 0
                        "
                    >
                        <span class="muted">
                            Ticket
                        </span>

                        <b class="price">
                            ${money(e.ticket_price)}
                        </b>
                    </div>

                    <button
                        class="btn block"
                        id="bookNow"
                    >
                        Book This Event
                    </button>

                </div>

            </div>
        `;

        $$('.slot').forEach(
            s=>{
                s.onclick=()=>{
                    $$('.slot').forEach(
                        x=>x.classList.remove('selected')
                    );

                    s.classList.add('selected');
                };
            }
        );

        $('#bookNow').onclick=async()=>{

            const u=
                await currentUser();

            if(!u){

                location.href=
                    `signin.html?return=${
                        encodeURIComponent(
                            `booking.html?event=${id}&slot=${
                                $('.slot.selected')
                                ?.dataset.slot || ''
                            }`
                        )
                    }`;

                return;
            }

            if(u.status==='rejected'){

                location.href='profile.html';

                return;
            }

            if(u.status!=='approved'){

                toast(
                    'Profile approval is required before booking.'
                );

                location.href='profile.html';

                return;
            }

            location.href=
                `booking.html?event=${id}&slot=${
                    $('.slot.selected')
                    ?.dataset.slot || ''
                }`;
        };

    }catch(e){

        $('#detail').innerHTML=
            `<div class="notice danger">${esc(e.message)}</div>`;
    }
}

async function logout(){

    await api(
        'auth/logout.php',
        {
            method:'POST'
        }
    ).catch(()=>{});

    location.href='index.html';
}

function fieldError(name,msg){

    const el=
        document.querySelector(
            `[data-error-for="${name}"]`
        );

    if(el){
        el.textContent=msg||'';
    }

    const actual=
        name.startsWith('edit_')
        ?name.slice(5)
        :name;

    const scope=
        el?.closest('.field') ||
        document;

    const input=
        scope.querySelector(
            `[name="${actual}"],#${actual}`
        );

    if(input){

        input.classList.toggle(
            'invalid',
            !!msg
        );

        input.setAttribute(
            'aria-invalid',
            msg
            ?'true'
            :'false'
        );
    }
}

function clearFieldErrors(form){

    form
        .querySelectorAll('.field-error')
        .forEach(
            x=>x.textContent=''
        );

    form
        .querySelectorAll('.invalid')
        .forEach(
            x=>x.classList.remove('invalid')
        );

    form
        .querySelectorAll(
            '[aria-invalid="true"]'
        )
        .forEach(
            x=>x.setAttribute(
                'aria-invalid',
                'false'
            )
        );
}

function alertBox(
    id,
    msg,
    type='danger'
){

    const el=$(`#${id}`);

    if(!el)return;

    el.className=
        `alert-box ${type}`;

    el.innerHTML=esc(msg);

    el.classList.remove('hidden');

    el.scrollIntoView({
        behavior:'smooth',
        block:'center'
    });
}

function setupPasswordToggles(){

    $$('[data-toggle-password]')
        .forEach(
            btn=>{
                btn.onclick=()=>{

                    const input=
                        $(`#${btn.dataset.togglePassword}`);

                    if(!input)return;

                    const show=
                        input.type==='password';

                    input.type=
                        show
                        ?'text'
                        :'password';

                    btn.textContent=
                        show
                        ?'◉'
                        :'◌';

                    btn.setAttribute(
                        'aria-label',
                        show
                        ?'Hide password'
                        :'Show password'
                    );
                };
            }
        );
}

function setupSignup(){

    const f=$('#signupForm');

    if(!f)return;

    wireAge();
    setupPasswordToggles();

    const same=$('#whatsapp_same'),
          wa=$('#waWrap'),
          dob=$('#dob'),
          ageBox=$('#age'),
          elig=$('#eligibilityBox'),
          confirm=$('#ageEligibleConfirm');

    const updateEligibility=()=>{

        const age=
            ageFromDob(dob.value);

        if(age===''){

            ageBox.value='';

            confirm.checked=false;
            confirm.disabled=true;

            elig.className='notice';

            elig.textContent=
                'Enter your date of birth to check age eligibility.';

            return;
        }

        if(age<18){

            confirm.checked=false;
            confirm.disabled=true;

            elig.className=
                'notice danger';

            elig.textContent=
                `Age eligibility failed. You are ${age} years old. You must be 18 or older.`;

        }else{

            confirm.disabled=false;

            elig.className=
                'notice success';

            elig.textContent=
                `Age eligibility passed. Your automatically calculated age is ${age}.`;
        }
    };

    dob?.addEventListener(
        'change',
        updateEligibility
    );

    updateEligibility();

    same?.addEventListener(
        'change',
        ()=>{
            wa.style.display=
                same.checked
                ?'none'
                :'block';

            if(same.checked){
                fieldError(
                    'whatsapp_number',
                    ''
                );
            }
        }
    );

    f.addEventListener(
        'submit',
        async ev=>{

            ev.preventDefault();

            clearFieldErrors(f);

            const fd=
                new FormData(f);

            let valid=true;

            const email=
                String(
                    fd.get('email')||''
                )
                .trim()
                .toLowerCase();

            const password=
                String(
                    fd.get('password')||''
                );

            const confirmPassword=
                String(
                    fd.get('confirm_password')||''
                );

            const full=
                String(
                    fd.get('full_name')||''
                )
                .trim();

            const mobile=
                String(
                    fd.get('mobile_number')||''
                )
                .trim();

            const waNumber=
                String(
                    fd.get('whatsapp_number')||''
                )
                .trim();

            const dobValue=
                String(
                    fd.get('dob')||''
                );

            const age=
                ageFromDob(dobValue);

            const doc=
                fd.get('doc_file');

            if(
                !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
            ){

                fieldError(
                    'email',
                    'Enter a valid email address.'
                );

                valid=false;
            }

            if(
                password.length<8 ||
                !/[A-Za-z]/.test(password) ||
                !/[0-9]/.test(password)
            ){

                fieldError(
                    'password',
                    'Password must be at least 8 characters and contain a letter and a number.'
                );

                valid=false;
            }

            if(confirmPassword!==password){

                fieldError(
                    'confirm_password',
                    'Passwords do not match.'
                );

                valid=false;
            }

            if(full.length<2){

                fieldError(
                    'full_name',
                    'Full name is required.'
                );

                valid=false;
            }

            if(
                !/^[6-9]\d{9}$/.test(mobile)
            ){

                fieldError(
                    'mobile_number',
                    'Enter a valid 10-digit Indian mobile number.'
                );

                valid=false;
            }

            if(
                !same.checked &&
                !/^[6-9]\d{9}$/.test(waNumber)
            ){

                fieldError(
                    'whatsapp_number',
                    'Enter a valid 10-digit WhatsApp number.'
                );

                valid=false;
            }

            if(!fd.get('gender')){

                fieldError(
                    'gender',
                    'Select gender.'
                );

                valid=false;
            }

            if(
                !dobValue ||
                age==='' ||
                age<18
            ){

                fieldError(
                    'dob',
                    'You must be 18 or older.'
                );

                valid=false;
            }

            if(!fd.get('marital_status')){

                fieldError(
                    'marital_status',
                    'Select marital status.'
                );

                valid=false;
            }

            if(!fd.get('doc_type')){

                fieldError(
                    'doc_type',
                    'Select a government document type.'
                );

                valid=false;
            }

            if(
                !doc ||
                !doc.name
            ){

                fieldError(
                    'doc_file',
                    'Upload your government identity document.'
                );

                valid=false;

            }else if(
                doc.size>8*1024*1024
            ){

                fieldError(
                    'doc_file',
                    'Document must be 8 MB or smaller.'
                );

                valid=false;
            }

            if(
                confirm.disabled ||
                !confirm.checked
            ){

                fieldError(
                    'ageEligibleConfirm',
                    'Confirm the age eligibility declaration.'
                );

                valid=false;
            }

            if(!valid){

                alertBox(
                    'signupAlert',
                    'Please correct the highlighted fields before submitting.'
                );

                return;
            }

            try{

                await api(
                    'auth/register.php',
                    {
                        method:'POST',
                        body:{
                            email,
                            password,
                            confirm_password:
                                confirmPassword
                        }
                    }
                );

                const profile=
                    new FormData();

                [
                    'full_name',
                    'mobile_number',
                    'gender',
                    'dob',
                    'marital_status',
                    'instagram_handle'
                ].forEach(
                    k=>profile.append(
                        k,
                        fd.get(k)||''
                    )
                );

                profile.append(
                    'whatsapp_same',
                    same.checked
                    ?'1'
                    :'0'
                );

                if(!same.checked){

                    profile.append(
                        'whatsapp_number',
                        waNumber
                    );
                }

                await api(
                    'profile/save.php',
                    {
                        method:'POST',
                        body:profile
                    }
                );

                const verify=
                    new FormData();

                verify.append(
                    'doc_type',
                    fd.get('doc_type')
                );

                verify.append(
                    'doc_file',
                    doc
                );

                const verifyResult=
                    await api(
                        'verification/submit.php',
                        {
                            method:'POST',
                            body:verify
                        }
                    );

                f.classList.add('hidden');

                $('#signupDone').innerHTML=`
                    <div class="status-card">

                        <div class="status-icon">
                            ✓
                        </div>

                        <h2>
                            Profile Submitted
                        </h2>

                        <p class="muted">
                            Your profile has been submitted and is under admin review.
                        </p>

                        <div
                            class="
                                notice
                                ${
                                    verifyResult.email_sent
                                    ?'success'
                                    :'danger'
                                }
                            "
                            style="
                                margin-top:18px;
                                text-align:left
                            "
                        >
                            <b>
                                ${
                                    verifyResult.email_sent
                                    ?'Email notification sent'
                                    :'Email notification could not be sent'
                                }
                            </b>

                            <br>

                            ${
                                verifyResult.email_sent
                                ?'We have sent the profile-review notification to your registered email address.'
                                :'Please check the SMTP/mail configuration in backend/config/config.php.'
                            }

                        </div>

                        <div
                            class="form-actions"
                            style="justify-content:center"
                        >

                            <a
                                class="btn"
                                href="signin.html"
                            >
                                Continue to Sign In
                            </a>

                            <a
                                class="btn secondary"
                                href="events.html"
                            >
                                Browse Events
                            </a>

                        </div>

                    </div>
                `;

                $('#signupDone')
                    .classList
                    .remove('hidden');

                window.scrollTo({
                    top:0,
                    behavior:'smooth'
                });

            }catch(e){

                alertBox(
                    'signupAlert',
                    e.message
                );
            }
        }
    );
}

function setupSignin(){

    const f=$('#signinForm');

    if(!f)return;

    f.addEventListener(
        'submit',
        async ev=>{

            ev.preventDefault();

            try{

                const r=
                    await api(
                        'auth/login.php',
                        {
                            method:'POST',
                            body:{
                                email:f.email.value,
                                password:f.password.value
                            }
                        }
                    );

                const q=
                    new URLSearchParams(
                        location.search
                    ).get('return');

                if(q){
                    location.href=q;
                }else{
                    location.href=
                        r.user.status==='approved'
                        ?'profile.html'
                        :'profile.html';
                }

            }catch(e){

                toast(e.message);
            }
        }
    );
}

async function loadProfile(){

    const box=$('#profileContent');

    if(!box)return;

    try{

        const r=
            await api('auth/me.php');

        const p=r.profile||{};

        const status=
            r.user.status;

        let statusNotice='';

        if(status==='pending_review'){

            statusNotice=`
                <div
                    class="notice"
                    style="margin-top:16px"
                >
                    <b>
                        Your profile is under admin review.
                    </b>

                    <br>

                    You will receive an email when it is approved or correction is required.
                </div>
            `;
        }

        if(status==='rejected'){

            statusNotice=`
                <div
                    class="notice danger"
                    style="margin-top:16px"
                >
                    <b>
                        Correction Required
                    </b>

                    <br>

                    ${esc(
                        r.user.rejection_reason ||
                        r.verification?.admin_notes ||
                        'Please correct your profile and resubmit.'
                    )}
                </div>
            `;
        }

        if(status==='approved'){

            statusNotice=`
                <div
                    class="notice success"
                    style="margin-top:16px"
                >
                    <b>
                        Profile Approved ✓
                    </b>

                    <br>

                    You can now book eligible events.
                </div>
            `;
        }

        box.innerHTML=`

            <div class="two-col">

                <div class="card card-pad">

                    <div class="kicker">
                        Account
                    </div>

                    <h2>
                        ${esc(
                            p.full_name ||
                            r.user.email
                        )}
                    </h2>

                    <p class="muted">
                        Username:
                        ${esc(r.user.username)}

                        <br>

                        Email:
                        ${esc(r.user.email)}
                    </p>

                    ${statusHtml(status)}

                    ${statusNotice}

                    <div
                        class="form-actions"
                        style="justify-content:flex-start"
                    >

                        <button
                            class="btn secondary"
                            id="editProfile"
                        >
                            Edit Profile
                        </button>

                        ${
                            status==='rejected'
                            ?`
                                <button
                                    class="btn"
                                    id="resubmitHint"
                                >
                                    Edit & Resubmit
                                </button>
                            `
                            :''
                        }

                    </div>

                </div>

                <div class="card card-pad">

                    <div class="kicker">
                        Verification
                    </div>

                    <h3>
                        Identity Document
                    </h3>

                    <p class="muted">
                        ${
                            r.verification
                            ?esc(
                                documentTypeLabel(
                                    r.verification.doc_type
                                )
                            )
                            :'Not submitted'
                        }
                    </p>

                    ${
                        r.verification
                        ?statusHtml(
                            r.verification.status
                        )
                        :''
                    }

                    ${
                        r.verification?.admin_notes
                        ?`
                            <div
                                class="notice danger"
                                style="margin-top:14px"
                            >
                                ${esc(
                                    r.verification.admin_notes
                                )}
                            </div>
                        `
                        :''
                    }

                    <hr
                        style="
                            border:0;
                            border-top:1px solid var(--line);
                            margin:18px 0
                        "
                    >

                    <div class="event-meta">

                        <span>
                            Mobile:
                            +91
                            ${esc(
                                p.mobile_number||''
                            )}
                        </span>

                        <span>
                            Age:
                            ${r.age??'—'}
                        </span>

                    </div>

                </div>

            </div>

            <div
                id="profileEdit"
                class="card card-pad"
                style="
                    margin-top:18px;
                    display:none
                "
            ></div>
        `;

        $('#editProfile').onclick=
            ()=>renderProfileForm(r);

        if(status==='rejected'){

            $('#resubmitHint')
                ?.addEventListener(
                    'click',
                    ()=>renderProfileForm(r)
                );
        }

    }catch(e){

        box.innerHTML=`
            <div class="notice danger">
                ${esc(e.message)}
                <a href="signin.html">
                    Sign in
                </a>
            </div>
        `;
    }
}

function documentTypeLabel(v){

    return ({
        aadhaar:'Aadhaar',
        pan:'PAN',
        voter_id:'Voter ID',
        dl:'Driving Licence',
        any_govt:'Any Government Document'
    })[v] ||
    v ||
    'Government Document';
}

function renderProfileForm(r){

    const p=r.profile||{};

    const edit=$('#profileEdit');

    edit.style.display='block';

    edit.innerHTML=`

        <div class="kicker">
            Update details
        </div>

        <h3>
            Edit Profile
        </h3>

        <div
            id="profileEditAlert"
            class="alert-box hidden"
            role="alert"
        ></div>

        <form
            id="editForm"
            class="form-grid"
            novalidate
        >

            <div class="field full">

                <label>
                    Full Name
                    <span class="required">*</span>
                </label>

                <input
                    name="full_name"
                    required
                    value="${esc(p.full_name)}"
                >

                <span
                    class="field-error"
                    data-error-for="edit_full_name"
                ></span>

            </div>

            <div class="field">

                <label>
                    Mobile (+91)
                    <span class="required">*</span>
                </label>

                <input
                    name="mobile_number"
                    required
                    maxlength="10"
                    inputmode="numeric"
                    value="${esc(p.mobile_number)}"
                >

                <span
                    class="field-error"
                    data-error-for="edit_mobile_number"
                ></span>

            </div>

            <div class="field">

                <label>
                    WhatsApp
                </label>

                <label class="checkline">

                    <input
                        type="checkbox"
                        name="whatsapp_same"
                        id="editSame"
                        ${p.whatsapp_same?'checked':''}
                    >

                    Same as mobile

                </label>

            </div>

            <div
                class="field full"
                id="editWaWrap"
                style="${
                    p.whatsapp_same
                    ?'display:none'
                    :''
                }"
            >

                <label>
                    WhatsApp Number
                    <span class="required">*</span>
                </label>

                <input
                    name="whatsapp_number"
                    maxlength="10"
                    inputmode="numeric"
                    value="${esc(
                        p.whatsapp_number||''
                    )}"
                >

                <span
                    class="field-error"
                    data-error-for="edit_whatsapp_number"
                ></span>

            </div>

            <div class="field">

                <label>
                    Gender
                    <span class="required">*</span>
                </label>

                <select
                    name="gender"
                    required
                >
                    ${
                        [
                            'male',
                            'female',
                            'other'
                        ].map(
                            x=>`
                                <option
                                    value="${x}"
                                    ${
                                        p.gender===x
                                        ?'selected'
                                        :''
                                    }
                                >
                                    ${
                                        x[0].toUpperCase()+
                                        x.slice(1)
                                    }
                                </option>
                            `
                        ).join('')
                    }
                </select>

            </div>

            <div class="field">

                <label>
                    Date of Birth
                    <span class="required">*</span>
                </label>

                <input
                    type="date"
                    id="editDob"
                    name="dob"
                    required
                    value="${esc(p.dob)}"
                >

                <span
                    class="field-error"
                    data-error-for="edit_dob"
                ></span>

            </div>

            <div class="field">

                <label>
                    Age · Automatic
                </label>

                <input
                    id="editAge"
                    readonly
                    value="${r.age??''}"
                >

            </div>

            <div class="field">

                <label>
                    Marital Status
                    <span class="required">*</span>
                </label>

                <select
                    name="marital_status"
                    required
                >
                    ${
                        [
                            [
                                'never_married',
                                'Never Married'
                            ],
                            [
                                'separated',
                                'Separated'
                            ],
                            [
                                'divorced',
                                'Divorced'
                            ],
                            [
                                'widowed',
                                'Widowed'
                            ]
                        ].map(
                            ([v,l])=>`
                                <option
                                    value="${v}"
                                    ${
                                        p.marital_status===v
                                        ?'selected'
                                        :''
                                    }
                                >
                                    ${l}
                                </option>
                            `
                        ).join('')
                    }
                </select>

            </div>

            <div class="field full">

                <label>
                    Instagram · Optional
                </label>

                <input
                    name="instagram_handle"
                    value="${esc(
                        p.instagram_handle||''
                    )}"
                >

            </div>

            <div class="field full">

                <div class="form-actions">

                    <button
                        class="btn secondary"
                        type="button"
                        id="cancelProfileEdit"
                    >
                        Cancel
                    </button>

                    <button
                        class="btn"
                        type="submit"
                    >
                        Save Changes
                    </button>

                </div>

            </div>

        </form>

        ${
            r.user.status==='rejected'
            ?`
                <hr class="form-sep">

                <div class="kicker">
                    Resubmit verification
                </div>

                <p class="muted">
                    Upload the corrected government document.
                    Document number is not collected.
                </p>

                <form
                    id="reverify"
                    class="form-grid"
                >

                    <div class="field">

                        <label>
                            Government Document
                            <span class="required">*</span>
                        </label>

                        <select
                            name="doc_type"
                            required
                        >
                            <option value="">
                                Select
                            </option>

                            <option value="aadhaar">
                                Aadhaar
                            </option>

                            <option value="pan">
                                PAN
                            </option>

                            <option value="voter_id">
                                Voter ID
                            </option>

                            <option value="dl">
                                Driving Licence
                            </option>

                            <option value="any_govt">
                                Any Government Document
                            </option>

                        </select>

                    </div>

                    <div class="field">

                        <label>
                            Upload Document
                            <span class="required">*</span>
                        </label>

                        <input
                            type="file"
                            name="doc_file"
                            required
                            accept=".jpg,.jpeg,.png,.pdf,.webp"
                        >

                    </div>

                    <div class="field full">

                        <button
                            class="btn"
                            type="submit"
                        >
                            Resubmit for Review
                        </button>

                    </div>

                </form>
            `
            :''
        }
    `;

    $('#cancelProfileEdit').onclick=
        ()=>edit.style.display='none';

    const d=$('#editDob'),
          a=$('#editAge');

    d.onchange=
        ()=>a.value=ageFromDob(d.value);

    $('#editSame').onchange=
        e=>{
            $('#editWaWrap').style.display=
                e.target.checked
                ?'none'
                :'block';
        };

    $('#editForm').onsubmit=
        async ev=>{

            ev.preventDefault();

            const fd=
                new FormData(ev.target);

            let ok=true;

            if(
                String(
                    fd.get('full_name')||''
                ).trim().length<2
            ){

                fieldError(
                    'edit_full_name',
                    'Full name is required.'
                );

                ok=false;
            }

            if(
                !/^[6-9]\d{9}$/.test(
                    String(
                        fd.get('mobile_number')||''
                    )
                )
            ){

                fieldError(
                    'edit_mobile_number',
                    'Enter a valid 10-digit mobile number.'
                );

                ok=false;
            }

            if(
                !$('#editSame').checked &&
                !/^[6-9]\d{9}$/.test(
                    String(
                        fd.get('whatsapp_number')||''
                    )
                )
            ){

                fieldError(
                    'edit_whatsapp_number',
                    'Enter a valid 10-digit WhatsApp number.'
                );

                ok=false;
            }

            if(
                ageFromDob(fd.get('dob'))<18
            ){

                fieldError(
                    'edit_dob',
                    'Age must be 18 or older.'
                );

                ok=false;
            }

            if(!ok){

                alertBox(
                    'profileEditAlert',
                    'Please correct the highlighted fields.'
                );

                return;
            }

            try{

                await api(
                    'profile/save.php',
                    {
                        method:'POST',
                        body:fd
                    }
                );

                alertBox(
                    'profileEditAlert',
                    'Profile updated successfully.',
                    'success'
                );

                if(
                    r.user.status==='rejected'
                ){

                    $('#profileEdit')
                        .scrollIntoView({
                            behavior:'smooth',
                            block:'start'
                        });

                }else{

                    setTimeout(
                        loadProfile,
                        700
                    );
                }

            }catch(e){

                alertBox(
                    'profileEditAlert',
                    e.message
                );
            }
        };

    $('#reverify')?.addEventListener(
        'submit',
        async ev=>{

            ev.preventDefault();

            const fd=
                new FormData(ev.target);

            try{

                const rr=
                    await api(
                        'verification/submit.php',
                        {
                            method:'POST',
                            body:fd
                        }
                    );

                alertBox(
                    'profileEditAlert',
                    rr.email_sent
                    ?'Profile resubmitted. Email notification sent and your verification is pending admin review.'
                    :'Profile resubmitted, but email notification could not be sent. Check SMTP configuration.',
                    'success'
                );

                setTimeout(
                    loadProfile,
                    900
                );

            }catch(e){

                alertBox(
                    'profileEditAlert',
                    e.message
                );
            }
        }
    );
}

async function loadBookings(){

    const box=$('#bookingsList');

    if(!box)return;

    try{

        const r=
            await api('bookings/my.php');

        box.innerHTML=
            r.bookings.length
            ?r.bookings.map(
                b=>`

                    <div class="card card-pad">

                        <div
                            style="
                                display:flex;
                                justify-content:space-between;
                                gap:12px;
                                flex-wrap:wrap
                            "
                        >

                            <div>

                                <div class="kicker">
                                    ${esc(b.booking_code)}
                                </div>

                                <h3>
                                    ${esc(b.event_title)}
                                </h3>

                                <p class="muted">
                                    ${fmtDate(b.event_date)}
                                    ·
                                    ${esc(b.venue)}
                                </p>

                            </div>

                            <div>

                                ${statusHtml(b.status)}

                                <div
                                    class="price"
                                    style="margin-top:10px"
                                >
                                    ${money(b.booking_amount)}
                                </div>

                            </div>

                        </div>

                    </div>
                `
            ).join('')
            :`
                <div class="notice">
                    No bookings yet.
                </div>
            `;

    }catch(e){

        box.innerHTML=
            `<div class="notice danger">${esc(e.message)}</div>`;
    }
}

async function loadPasses(){

    const box = $('#passesList');

    if (!box) return;

    try {

        const r = await api('passes/my.php');

        box.innerHTML = r.passes.length
            ? r.passes.map(p => `

                <article class="pass">

                    <div class="pass-top">

                        <div>

                            <img
                                class="pass-logo"
                                src="assets/hello-hobnobbers-logo.png"
                                alt="Hello Hobnobbers"
                            >

                            <div class="kicker">
                                ${esc(p.event_title)}
                            </div>

                            <h2>
                                ${fmtDate(p.event_date)}
                            </h2>

                            <p class="muted">
                                ${esc(p.venue)}
                                ·
                                ${esc(p.event_start_time.slice(0,5))}
                                –
                                ${esc(p.event_end_time.slice(0,5))}
                            </p>

                        </div>

                        <div>

                            <div
                                class="qr-wrap qr"
                                data-pass-token="${esc(p.pass_token)}"
                            ></div>

                        </div>

                    </div>

                    <div class="pass-body">

                        <div class="grid grid-2">

                            <div>

                                <span class="muted">
                                    Guest
                                </span>

                                <h3>
                                    ${esc(p.full_name || 'Guest')}
                                </h3>

                            </div>

                            <div>

                                <span class="muted">
                                    Booking ID
                                </span>

                                <h3 class="pass-code">
                                    ${esc(p.booking_code)}
                                </h3>

                            </div>

                        </div>

                        <div style="margin-top:18px">

                            <span class="muted">
                                Pass Code
                            </span>

                            <h3 class="pass-code">
                                ${esc(p.pass_token)}
                            </h3>

                        </div>

                        <p class="muted">

                            ${esc(
                                p.important_information ||
                                p.eligibility ||
                                'Carry a valid ID. Entry is subject to event rules.'
                            )}

                        </p>

                        <div class="pass-actions">

                            <button
                                class="btn"
                                onclick="window.print()"
                            >
                                Download / Print Pass
                            </button>

                            <button
                                class="btn secondary"
                                onclick="
                                    navigator.share
                                    ? navigator.share({
                                        title:'Hello Hobnobbers Pass',
                                        text:'Pass ${esc(p.pass_token)}'
                                    })
                                    : toast('Share is not supported on this device.')
                                "
                            >
                                Share Pass
                            </button>

                        </div>

                    </div>

                </article>

            `).join('')

            : `
                <div class="notice">
                    No event passes generated yet.
                </div>
            `;

        /* Generate QR codes using each user's actual pass token */
        generatePassQRCodes();

    } catch (e) {

        box.innerHTML =
            `<div class="notice danger">${esc(e.message)}</div>`;
    }
}
function generatePassQRCodes(){

    document
        .querySelectorAll('.qr[data-pass-token]')
        .forEach(qr => {

            const token =
                qr.dataset.passToken;

            if(!token)return;

            if(typeof QRCode === 'undefined'){

                console.error(
                    'QRCode library not loaded.'
                );

                return;
            }

            qr.innerHTML = '';

            new QRCode(qr, {
                text: token,
                width: 140,
                height: 140
            });

        });
}

async function loadNotifications(){

    const box=$('#notificationsList');

    if(!box)return;

    try{

        const r=
            await api('notifications/my.php');

        box.innerHTML=
            r.notifications.length
            ?r.notifications.map(
                n=>`

                    <div class="card card-pad">

                        <div
                            style="
                                display:flex;
                                justify-content:space-between;
                                gap:10px
                            "
                        >

                            <b>
                                ${esc(n.title)}
                            </b>

                            <span class="muted">
                                ${
                                    new Date(
                                        n.created_at
                                    ).toLocaleString(
                                        'en-IN'
                                    )
                                }
                            </span>

                        </div>

                        <p class="muted">
                            ${esc(n.message)}
                        </p>

                    </div>
                `
            ).join('')
            :`
                <div class="notice">
                    No notifications.
                </div>
            `;

    }catch(e){

        box.innerHTML=
            `<div class="notice danger">${esc(e.message)}</div>`;
    }
}


/* =========================================================
   BOOKING + PAYMENT
   ========================================================= */

async function loadBooking(){

    const params=
        new URLSearchParams(
            location.search
        );

    const id=
        params.get('event');

    const slot=
        params.get('slot');

    if(!id)return;

    try{

        const [
            r,
            m
        ]=await Promise.all([
            api(`events/details.php?id=${id}`),
            api('auth/me.php')
        ]);

        const u=m.user;

        if(!u){

            location.href=
                `signin.html?return=${
                    encodeURIComponent(
                        `booking.html?event=${id}&slot=${slot||''}`
                    )
                }`;

            return;
        }

        if(u.status!=='approved'){

            $('#booking').innerHTML=`
                <div class="notice danger">

                    Your profile must be approved before booking.

                    <a href="profile.html">
                        Open My Profile
                    </a>

                </div>
            `;

            return;
        }

        const e=r.event;

        $('#booking').innerHTML=`

            <div class="two-col">

                <div class="card card-pad">

                    <div class="kicker">
                        Booking details
                    </div>

                    <h1>
                        ${esc(e.title)}
                    </h1>

                    <p class="muted">
                        ${fmtDate(e.event_date)}
                        ·
                        ${esc(
                            e.start_time.slice(0,5)
                        )}
                        –
                        ${esc(
                            e.end_time.slice(0,5)
                        )}
                        ·
                        ${esc(e.venue)}
                    </p>

                    <div class="notice">

                        <b>
                            Before payment
                        </b>

                        <br>

                        Event Name:
                        ${esc(e.title)}

                        <br>

                        Date & time:
                        ${fmtDate(e.event_date)}
                        ·
                        ${esc(
                            e.start_time.slice(0,5)
                        )}

                        <br>

                        Venue:
                        ${esc(e.venue)}

                        <br>

                        User Name:
                        ${esc(
                            m.profile?.full_name ||
                            u.email
                        )}

                        <br>

                        Mobile:
                        +91
                        ${esc(
                            m.profile?.mobile_number ||
                            ''
                        )}

                        <br>

                        Email:
                        ${esc(u.email)}

                    </div>

                    <div class="slot-list">

                        ${
                            r.slots.map(
                                s=>`

                                    <button
                                        class="
                                            slot
                                            ${
                                                String(s.id)===String(slot)
                                                ?'selected'
                                                :''
                                            }
                                        "
                                        data-slot="${s.id}"
                                        data-slot-label="${esc(
                                            s.slot_label
                                        )}"
                                    >

                                        ${esc(
                                            s.slot_label
                                        )}

                                        ·

                                        ${esc(
                                            s.start_time.slice(0,5)
                                        )}

                                        -

                                        ${esc(
                                            s.end_time.slice(0,5)
                                        )}

                                    </button>

                                `
                            ).join('')
                        }

                    </div>

                </div>

                <div class="card card-pad">

                    <div class="kicker">
                        Order summary
                    </div>

                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            margin:15px 0
                        "
                    >

                        <span class="muted">
                            Ticket Price
                        </span>

                        <b>
                            ${money(e.ticket_price)}
                        </b>

                    </div>

                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            border-top:1px solid var(--line);
                            padding-top:15px;
                            font-size:1.2rem
                        "
                    >

                        <span>
                            Total Amount
                        </span>

                        <b class="price">
                            ${money(e.ticket_price)}
                        </b>

                    </div>

                    <button
                        class="btn block"
                        id="proceedPay"
                        style="margin-top:22px"
                    >
                        Proceed to Payment
                    </button>

                </div>

            </div>
        `;

        if(!$('.slot.selected')){

            $('.slot')?.classList.add(
                'selected'
            );
        }

        $$('.slot').forEach(
            s=>{
                s.onclick=()=>{

                    $$('.slot').forEach(
                        x=>x.classList.remove(
                            'selected'
                        )
                    );

                    s.classList.add(
                        'selected'
                    );
                };
            }
        );


        /* =====================================================
           PROCEED TO PAYMENT
           ===================================================== */

        $('#proceedPay').onclick=async()=>{

            try{

                const selected=
                    $('.slot.selected');

                if(!selected){

                    toast(
                        'Select an entry slot.'
                    );

                    return;
                }

                const selectedSlotId=
                    Number(
                        selected.dataset.slot
                    );

                const selectedSlotLabel =
                        selected.dataset.slotLabel ||
                                'General Entry';


                /*
                 * STEP 1
                 * Check whether this user already has
                 * a pending booking for this event + slot.
                 */

                const myBookings=
                    await api('bookings/my.php');
                    console.log('MY BOOKINGS:', myBookings);
console.log('EVENT:', e);
console.log('BOOKING ROW:', myBookings.bookings[0]);
console.log('SELECTED SLOT:', selectedSlotLabel);
                let existingBooking=
                    myBookings.bookings.find(
                        b=>{

                            const sameEvent=
                                String(
                                    b.event_title
                                ).trim()===
                                String(
                                    e.event_name
                                ).trim();

                            const sameSlot=
                                String(
                                    b.slot_label||''
                                ).trim()===
                                String(
                                    selectedSlotLabel
                                ).trim();

                            const pending=
                                String(
                                    b.status
                                ).toUpperCase()==='PENDING';

                            return (
                                sameEvent &&
                                sameSlot &&
                                pending
                            );
                        }
                    );


                /*
                 * STEP 2
                 * If pending booking already exists,
                 * reuse it.
                 *
                 * Otherwise create a new booking.
                 */

                let booking;

                if(existingBooking){

                    booking={
                        booking_id:
                            Number(
                                existingBooking.id
                            ),

                        booking_code:
                            existingBooking.booking_code,

                        amount:
                            existingBooking.booking_amount,

                        payment_status:
                            existingBooking.payment_status,

                        booking_status:
                            existingBooking.status
                    };

                }else{

                    booking=
                        await api(
                            'bookings/create.php',
                            {
                                method:'POST',

                                body:{
                                    event_id:Number(id),
                                    slot_id:selectedSlotId
                                }
                            }
                        );
                }


                /*
                 * STEP 3
                 * Create Razorpay order.
                 */

                const order=
                    await api(
                        'payments/create-order.php',
                        {
                            method:'POST',

                            body:{
                                booking_id:
                                    Number(
                                        booking.booking_id
                                    )
                            }
                        }
                    );


                /*
                 * STEP 4
                 * Check Razorpay SDK.
                 */

                if(!window.Razorpay){

                    toast(
                        'Razorpay SDK could not load.'
                    );

                    return;
                }


                /*
                 * STEP 5
                 * Open Razorpay payment window.
                 */

                const rzp=
                    new Razorpay({

                        key:
                            order.razorpay_key_id,

                        amount:
                            order.amount,

                        currency:
                            order.currency,

                        name:
                            'Hello Hobnobbers',

                        description:
                            e.title,

                        order_id:
                            order.order_id,

                        handler:
                            async res=>{

                                try{

                                    /*
                                     * STEP 6
                                     * Verify Razorpay payment.
                                     */

                                    await api(
                                        'payments/verify.php',
                                        {
                                            method:'POST',

                                            body:{
                                                razorpay_order_id:
                                                    res.razorpay_order_id,

                                                razorpay_payment_id:
                                                    res.razorpay_payment_id,

                                                razorpay_signature:
                                                    res.razorpay_signature
                                            }
                                        }
                                    );


                                    /*
                                     * STEP 7
                                     * Payment successful.
                                     * Open Event Pass.
                                     */

                                    location.href=
                                        `passes.html?success=${
                                            encodeURIComponent(
                                                booking.booking_code
                                            )
                                        }`;

                                }catch(x){

                                    toast(
                                        x.message
                                    );
                                }
                            },

                        theme:{
                            color:'#f7c85a'
                        }
                    });

                rzp.open();

            }catch(x){

                toast(
                    x.message
                );
            }
        };

    }catch(e){

        $('#booking').innerHTML=
            `<div class="notice danger">${esc(e.message)}</div>`;
    }
}

function setupFileLabels(){

    $$('input[type=file]')
        .forEach(
            i=>{
                i.addEventListener(
                    'change',
                    ()=>{
                        const n=
                            i.files?.[0]?.name;

                        if(n){
                            toast(
                                `Selected: ${n}`
                            );
                        }
                    }
                );
            }
        );
}

document.addEventListener(
    'DOMContentLoaded',
    async()=>{
        theme();

        await buildNav();

        wireAge();

        setupSignup();

        setupSignin();

        loadHome();

        loadEvents();

        loadEventDetails();

        loadProfile();

        loadBookings();

        loadPasses();
        generatePassQRCodes();

        loadNotifications();

        loadBooking();

        setupFileLabels();
    }
);