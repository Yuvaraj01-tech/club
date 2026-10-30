
async function events(){
    const box = $('#eventsTable');
    if(!box) return;

    try{
        const st = $('#eventStatus')?.value || '';

        const r = await api(
            'admin/events/list.php' + (st ? '?status=' + st : '')
        );

        box.innerHTML = table(
            r.events,
            ['Event','Date & venue','Price','Capacity','Status'],
            e => `
                <tr>
                    <td>
                        <b>${esc(e.title)}</b>
                        <br>
                        <span class="muted">${esc(e.category || '')}</span>
                    </td>

                    <td>
                        ${fmtDate(e.event_date)}
                        <br>
                        ${esc(e.venue)}
                    </td>

                    <td>
                        ${money(e.ticket_price)}
                    </td>

                    <td>
                        ${e.booked_count || 0}/${e.total_capacity || e.capacity || 0}
                    </td>

                    <td>
                        <span class="pill">${esc(e.status)}</span>
                    </td>
                </tr>
            `
        );

    } catch(e) {
        box.innerHTML = `
            <div class="notice danger">
                ${esc(e.message || 'Could not load events.')}
            </div>
        `;
    }
}

events();


async function checkin() {
    const form = document.getElementById('scanForm');
    const result = document.getElementById('scanResult');

    if (!form || !result) return;

    const passCode =
        form.elements['pass_code']?.value.trim();

    const eventId =
        form.elements['event_id']?.value.trim();

    if (!passCode) {
        result.innerHTML = `
            <div class="status-card">
                <div class="status-icon warn">?</div>
                <h2>Pass Code Required</h2>
                <p class="muted">Please enter a pass code.</p>
            </div>
        `;
        return;
    }

    result.innerHTML = `
        <div class="status-card">
            <div class="status-icon warn">...</div>
            <h2>Verifying...</h2>
            <p class="muted">Please wait.</p>
        </div>
    `;

    try {
        const body = {
            pass_code: passCode
        };

        if (eventId) {
            body.event_id = eventId;
        }

        const r = await window.api('checkin/scan.php', {
            method: 'POST',
            body
        });

        result.innerHTML = `
            <div class="status-card">
                <div class="status-icon success">✓</div>
                <h2>ENTRY APPROVED</h2>
                <p><strong>${esc(r.guest_name || 'Guest')}</strong></p>
                <p>${esc(r.event_title || '')}</p>
                <p>${esc(r.event_date || '')} · ${esc(r.venue || '')}</p>
                <p>Booking: ${esc(r.booking_code || '')}</p>
                <p>Slot: ${esc(r.slot_name || '')}</p>
            </div>
        `;

        form.reset();

    } catch (e) {
        result.innerHTML = `
            <div class="status-card">
                <div class="status-icon danger">✕</div>
                <h2>ENTRY NOT APPROVED</h2>
                <p class="muted">
                    ${esc(e.message || 'Invalid pass.')}
                </p>
            </div>
        `;
    }
}


const scanForm =
    document.getElementById('scanForm');

if (scanForm) {
    scanForm.addEventListener('submit', function(e) {
        e.preventDefault();
        checkin();
    });
}


async function verification() {
    const box =
        document.getElementById('verificationTable');

    if (!box) return;

    try {
        const status =
            document.getElementById('verificationStatus')?.value ||
            'pending';

        box.innerHTML = `
            <div class="notice">
                Loading…
            </div>
        `;

        const r = await api(
            'admin/verification/list.php?status=' +
            encodeURIComponent(status)
        );

        const rows = r.verifications || [];

        if (!rows.length) {
            box.innerHTML = `
                <div class="notice">
                    No ${esc(status)} verification records found.
                </div>
            `;
            return;
        }

        box.innerHTML = table(
            rows,
            ['User', 'Contact', 'Document', 'Status', 'Action'],
            v => `
                <tr>
                    <td>
                        <b>
                            ${esc(v.full_name || v.username || '')}
                        </b>
                        <br>
                        <span class="muted">
                            ${esc(v.username || '')}
                        </span>
                    </td>

                    <td>
                        ${esc(v.mobile_number || '')}
                        <br>
                        <span class="muted">
                            ${esc(v.gender || '')}
                        </span>
                    </td>

                    <td>
                        ${esc(v.doc_type || '')}
                    </td>

                    <td>
                        <span class="pill">
                            ${esc(v.status || '')}
                        </span>
                    </td>

                    <td>
                        <button
                            class="btn small"
                            type="button"
                            onclick="openVerification(${Number(v.verification_id)})">
                            View
                        </button>
                    </td>
                </tr>
            `
        );

    } catch (e) {
        box.innerHTML = `
            <div class="notice danger">
                ${esc(
                    e.message ||
                    'Could not load verification records.'
                )}
            </div>
        `;
    }
}


/*
 * IMPORTANT:
 * openVerification() is OUTSIDE verification().
 */

async function openVerification(id) {
    const modal =
        document.getElementById('verificationModal');

    const body =
        document.getElementById('verificationModalBody');

    if (!modal || !body) return;

    const status =
        document.getElementById('verificationStatus')?.value ||
        'pending';

    try {
        const r = await api(
            'admin/verification/list.php?status=' +
            encodeURIComponent(status)
        );

        const item =
            (r.verifications || []).find(
                v =>
                    Number(v.verification_id) ===
                    Number(id)
            );

        if (!item) {
            throw new Error(
                'Verification record not found.'
            );
        }

        body.innerHTML = `
            <div class="profile-details">

                <p>
                    <strong>Name:</strong>
                    ${esc(item.full_name || '-')}
                </p>

                <p>
                    <strong>Username:</strong>
                    ${esc(item.username || '-')}
                </p>

                <p>
                    <strong>Mobile:</strong>
                    ${esc(item.mobile_number || '-')}
                </p>

                <p>
                    <strong>Gender:</strong>
                    ${esc(item.gender || '-')}
                </p>

                <p>
                    <strong>Date of Birth:</strong>
                    ${esc(item.dob || '-')}
                </p>

                <p>
                    <strong>Marital Status:</strong>
                    ${esc(item.marital_status || '-')}
                </p>

                <p>
                    <strong>Instagram:</strong>
                    ${esc(item.instagram_handle || '-')}
                </p>

                <p>
                    <strong>Document:</strong>
                    ${esc(item.doc_type || '-')}
                </p>

                <p>
                    <strong>Verification Status:</strong>
                    ${esc(item.status || '-')}
                </p>

                <p>
                    <strong>Submitted:</strong>
                    ${esc(item.submitted_at || '-')}
                </p>

            </div>
        `;

        modal.classList.remove('hidden');

    } catch (e) {

        body.innerHTML = `
            <div class="notice danger">
                ${esc(
                    e.message ||
                    'Could not load verification details.'
                )}
            </div>
        `;

        modal.classList.remove('hidden');
    }
}


const closeVerificationModal =
    document.getElementById(
        'closeVerificationModal'
    );

if (closeVerificationModal) {

    closeVerificationModal.addEventListener(
        'click',
        function() {

            document
                .getElementById('verificationModal')
                ?.classList.add('hidden');

        }
    );
}


if (
    document.getElementById(
        'verificationTable'
    )
) {
    verification();
}

