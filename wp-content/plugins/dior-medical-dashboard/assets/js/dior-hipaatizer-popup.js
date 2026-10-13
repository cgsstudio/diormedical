(function () {
    'use strict';

    const sourceId = 'dior-hipaatizer-form-source-v17';
    let source = null;
    let originalParent = null;
    let originalNextSibling = null;

    function isAppointmentButton(element) {
        if (element.closest('#dior-docbooker-modal') || element.closest('.dior-booking-banner-card')) {
            return false;
        }
        const text = (element.innerText || element.textContent || element.value || '')
            .replace(/\s+/g, ' ').trim().toLowerCase();
        return text.includes('book an appointment') && !text.includes('docbooker');
    }

    function openPopup() {
        if (!window.Swal) {
            window.alert('Appointment form is not ready. Please try again.');
            return;
        }

        source = document.getElementById(sourceId);
        if (!source || !source.firstElementChild) {
            window.Swal.fire({
                icon: 'error',
                title: 'Form unavailable',
                text: 'The HIPAAtizer form could not be loaded. Please refresh and try again.'
            });
            return;
        }

        originalParent = source.parentNode;
        originalNextSibling = source.nextSibling;

        window.Swal.fire({
            title: 'Book An Appointment',
            html: '<div id="dior-hipaatizer-popup-slot-v17"></div>',
            width: 'min(1120px, 96vw)',
            showCloseButton: false,
            showConfirmButton: false,
            showCancelButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            focusConfirm: false,
            heightAuto: false,
            customClass: {
                popup: 'dior-hipaatizer-popup-v17',
                title: 'dior-hipaatizer-popup-title-v17',
                htmlContainer: 'dior-hipaatizer-popup-content-v17'
            },
            didOpen: function () {
                const slot = document.getElementById('dior-hipaatizer-popup-slot-v17');
                if (slot && source) {
                    source.hidden = false;
                    slot.appendChild(source);
                }
            },
            willClose: function () {
                if (source && originalParent) {
                    source.hidden = true;
                    if (originalNextSibling && originalNextSibling.parentNode === originalParent) {
                        originalParent.insertBefore(source, originalNextSibling);
                    } else {
                        originalParent.appendChild(source);
                    }
                }
            }
        });
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('button, a, [role="button"], input[type="button"], input[type="submit"]');
        if (!button || !isAppointmentButton(button)) return;

        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) event.stopImmediatePropagation();
        openPopup();
    }, true);
})();