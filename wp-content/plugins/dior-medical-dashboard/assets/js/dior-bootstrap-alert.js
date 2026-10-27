/**
 * DIOR Medical Dashboard — Native Bootstrap SweetAlert Replacement Engine
 * Version: 17.2.0
 * 
 * Replaces SweetAlert2 with 100% Native Bootstrap 5 Modals & Toasts.
 * Eliminates all external SweetAlert CSS & JS dependencies.
 * 
 * Provides:
 * - window.Swal
 * - window.bootstrapAlert
 * - bootstrap.Swal
 */
(function () {
    'use strict';

    if (typeof window !== 'undefined' && window.addEventListener) {
        window.addEventListener('unhandledrejection', function (e) {
            var r = e ? (e.reason || e) : null;
            var m = r ? (r.message || (typeof r === 'string' ? r : '')) : '';
            if (typeof m === 'string' && (
                m.indexOf('Could not establish connection') !== -1 ||
                m.indexOf('Receiving end does not exist') !== -1 ||
                m.indexOf('message port closed') !== -1
            )) {
                if (e.preventDefault) e.preventDefault();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            }
        });
    }

    var currentModalInstance = null;
    var currentModalElement = null;

    function getSvgIcon(iconType) {
        switch (iconType) {
            case 'success':
                return '<svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="dior-bs-icon-success">' +
                    '<circle cx="12" cy="12" r="10" stroke="#10B981" stroke-opacity="0.2" fill="#ECFDF5"/>' +
                    '<path d="M8 12.5l2.5 2.5L16 9" stroke="#10B981" stroke-width="2.5"/>' +
                    '</svg>';
            case 'error':
            case 'danger':
                return '<svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="dior-bs-icon-error">' +
                    '<circle cx="12" cy="12" r="10" stroke="#EF4444" stroke-opacity="0.2" fill="#FEF2F2"/>' +
                    '<line x1="15" y1="9" x2="9" y2="15"/>' +
                    '<line x1="9" y1="9" x2="15" y2="15"/>' +
                    '</svg>';
            case 'warning':
                return '<svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="dior-bs-icon-warning">' +
                    '<circle cx="12" cy="12" r="10" stroke="#F59E0B" stroke-opacity="0.2" fill="#FFFBEB"/>' +
                    '<line x1="12" y1="8" x2="12" y2="12" stroke-width="2.5"/>' +
                    '<line x1="12" y1="16" x2="12.01" y2="16" stroke-width="3"/>' +
                    '</svg>';
            case 'info':
                return '<svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#00346C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="dior-bs-icon-info">' +
                    '<circle cx="12" cy="12" r="10" stroke="#00346C" stroke-opacity="0.2" fill="#EFF6FF"/>' +
                    '<line x1="12" y1="16" x2="12" y2="12" stroke-width="2.5"/>' +
                    '<line x1="12" y1="8" x2="12.01" y2="8" stroke-width="3"/>' +
                    '</svg>';
            case 'question':
                return '<svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="dior-bs-icon-question">' +
                    '<circle cx="12" cy="12" r="10" stroke="#6366F1" stroke-opacity="0.2" fill="#EEF2FF"/>' +
                    '<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>' +
                    '<line x1="12" y1="17" x2="12.01" y2="17" stroke-width="3"/>' +
                    '</svg>';
            default:
                return '';
        }
    }

    function getSmallToastSvg(iconType) {
        switch (iconType) {
            case 'success':
                return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" fill="#ECFDF5"/><path d="M8 12.5l2.5 2.5L16 9"/></svg>';
            case 'error':
            case 'danger':
                return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" fill="#FEF2F2"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
            case 'warning':
                return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" fill="#FFFBEB"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
            case 'info':
            default:
                return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#00346C" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" fill="#EFF6FF"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
        }
    }

    function ensureToastContainer(position) {
        var posClass = 'top-0 end-0';
        if (position === 'top-start') posClass = 'top-0 start-0';
        else if (position === 'bottom-end') posClass = 'bottom-0 end-0';
        else if (position === 'bottom-start') posClass = 'bottom-0 start-0';
        else if (position === 'center' || position === 'top') posClass = 'top-0 start-50 translate-middle-x';

        var container = document.querySelector('.toast-container.dior-bs-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container dior-bs-toast-container position-fixed p-3 ' + posClass;
            container.style.zIndex = '999999';
            document.body.appendChild(container);
        }
        return container;
    }

    function fireToast(options) {
        return new Promise(function (resolve) {
            var container = ensureToastContainer(options.position);
            var timer = options.timer || 3000;

            var toastEl = document.createElement('div');
            toastEl.className = 'toast align-items-center border-0 shadow dior-bs-toast show';
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            toastEl.style.borderRadius = '12px';
            toastEl.style.background = '#FFFFFF';
            toastEl.style.border = '1px solid #E2E8F0';
            toastEl.style.boxShadow = '0 10px 25px -5px rgba(0, 52, 108, 0.1), 0 8px 10px -6px rgba(0, 52, 108, 0.1)';
            toastEl.style.overflow = 'hidden';

            var iconHtml = options.icon ? getSmallToastSvg(options.icon) : '';
            var title = options.title || options.text || '';

            toastEl.innerHTML =
                '<div class="d-flex align-items-center p-2 px-3">' +
                    (iconHtml ? '<div class="me-2 d-flex align-items-center">' + iconHtml + '</div>' : '') +
                    '<div class="toast-body p-0 text-dark fw-semibold" style="font-size: 13.5px; line-height: 1.4;">' + title + '</div>' +
                    '<button type="button" class="btn-close ms-auto my-auto" style="font-size: 11px;" aria-label="Close"></button>' +
                '</div>' +
                (options.timerProgressBar ? '<div class="progress" style="height: 3px; border-radius: 0;"><div class="progress-bar bg-primary" style="width: 100%; transition: width ' + (timer / 1000) + 's linear;"></div></div>' : '');

            container.appendChild(toastEl);

            var closeBtn = toastEl.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    removeToast();
                });
            }

            if (options.timerProgressBar) {
                setTimeout(function () {
                    var bar = toastEl.querySelector('.progress-bar');
                    if (bar) bar.style.width = '0%';
                }, 50);
            }

            function removeToast() {
                toastEl.classList.remove('show');
                setTimeout(function () {
                    if (toastEl.parentNode) toastEl.parentNode.removeChild(toastEl);
                    resolve({ isConfirmed: true, isDismissed: false });
                }, 200);
            }

            if (timer) {
                setTimeout(removeToast, timer);
            }
        });
    }

    function fireModal(options) {
        return new Promise(function (resolve) {
            // Close any existing modal instance
            if (currentModalInstance && currentModalElement) {
                try { currentModalInstance.hide(); } catch (e) {}
                if (currentModalElement.parentNode) {
                    currentModalElement.parentNode.removeChild(currentModalElement);
                }
            }

            var modalEl = document.createElement('div');
            modalEl.className = 'modal fade dior-bs-alert-modal';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('role', 'dialog');
            modalEl.setAttribute('aria-modal', 'true');
            modalEl.style.zIndex = '999998';

            var width = options.width || '440px';
            var showCancel = !!options.showCancelButton;
            var showConfirm = options.showConfirmButton !== false;
            var showClose = !!options.showCloseButton;
            var confirmText = options.confirmButtonText || 'OK';
            var cancelText = options.cancelButtonText || 'Cancel';
            var confirmColor = options.confirmButtonColor || '#00346C';
            var cancelColor = options.cancelButtonColor || '#64748B';

            var iconHtml = options.icon ? '<div class="dior-bs-alert-icon mb-3 d-flex justify-content-center">' + getSvgIcon(options.icon) + '</div>' : '';
            var titleHtml = options.title ? '<h4 class="modal-title fw-bold text-center text-dark mb-2" style="font-size: 20px; letter-spacing: -0.01em;">' + options.title + '</h4>' : '';
            var bodyContent = options.html || options.text || '';
            var bodyHtml = bodyContent ? '<div class="modal-body-text text-center text-muted" style="font-size: 14px; line-height: 1.55; color: #475569 !important;">' + bodyContent + '</div>' : '';

            var customPopupClass = (options.customClass && options.customClass.popup) ? ' ' + options.customClass.popup : '';

            modalEl.innerHTML =
                '<div class="modal-dialog modal-dialog-centered" style="max-width: ' + width + '; margin: 1.75rem auto;">' +
                    '<div class="modal-content border-0 shadow-lg' + customPopupClass + '" style="border-radius: 16px; overflow: hidden; background: #FFFFFF;">' +
                        (showClose ? '<div class="position-absolute top-0 end-0 p-3" style="z-index: 10;"><button type="button" class="btn-close dior-bs-btn-close" aria-label="Close"></button></div>' : '') +
                        '<div class="modal-body p-4 pt-4 pb-3">' +
                            iconHtml +
                            titleHtml +
                            bodyHtml +
                        '</div>' +
                        (showConfirm || showCancel ?
                            '<div class="modal-footer border-0 justify-content-center p-4 pt-1 gap-2" style="background: transparent;">' +
                                (showCancel ? '<button type="button" class="btn btn-secondary dior-bs-btn-cancel px-4 py-2" style="border-radius: 8px; font-weight: 600; font-size: 13.5px; background: ' + cancelColor + '; border-color: ' + cancelColor + ';">' + cancelText + '</button>' : '') +
                                (showConfirm ? '<button type="button" class="btn btn-primary dior-bs-btn-confirm px-4 py-2" style="border-radius: 8px; font-weight: 600; font-size: 13.5px; background: ' + confirmColor + '; border-color: ' + confirmColor + ';">' + confirmText + '</button>' : '') +
                            '</div>' : '') +
                    '</div>' +
                '</div>';

            document.body.appendChild(modalEl);
            currentModalElement = modalEl;

            var resolved = false;
            function finish(result) {
                if (resolved) return;
                resolved = true;
                if (typeof options.willClose === 'function') {
                    try { options.willClose(modalEl); } catch (e) {}
                }
                if (bsModal) {
                    try { bsModal.hide(); } catch (e) {}
                }
                setTimeout(function () {
                    if (modalEl.parentNode) modalEl.parentNode.removeChild(modalEl);
                    if (typeof options.didClose === 'function') {
                        try { options.didClose(); } catch (e) {}
                    }
                    currentModalInstance = null;
                    currentModalElement = null;
                    resolve(result);
                }, 200);
            }

            var confirmBtn = modalEl.querySelector('.dior-bs-btn-confirm');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function () {
                    finish({ isConfirmed: true, isDenied: false, isDismissed: false, value: true });
                });
            }

            var cancelBtn = modalEl.querySelector('.dior-bs-btn-cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    finish({ isConfirmed: false, isDenied: false, isDismissed: true, dismiss: 'cancel' });
                });
            }

            var closeBtn = modalEl.querySelector('.dior-bs-btn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    finish({ isConfirmed: false, isDenied: false, isDismissed: true, dismiss: 'close' });
                });
            }

            modalEl.addEventListener('hidden.bs.modal', function () {
                finish({ isConfirmed: false, isDenied: false, isDismissed: true, dismiss: 'backdrop' });
            });

            // Initialize Bootstrap modal instance
            var bsModal = null;
            if (window.bootstrap && window.bootstrap.Modal) {
                bsModal = new window.bootstrap.Modal(modalEl, {
                    backdrop: options.allowOutsideClick === false ? 'static' : true,
                    keyboard: options.allowEscapeKey !== false
                });
                currentModalInstance = bsModal;
                bsModal.show();
            } else {
                // Fallback if bootstrap JS bundle is executing asynchronously
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                var backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
                var origFinish = finish;
                finish = function (result) {
                    if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
                    origFinish(result);
                };
            }

            if (typeof options.didOpen === 'function') {
                try {
                    setTimeout(function () {
                        options.didOpen(modalEl);
                    }, 50);
                } catch (e) {}
            }

            if (options.timer) {
                setTimeout(function () {
                    finish({ isConfirmed: false, isDenied: false, isDismissed: true, dismiss: 'timer' });
                }, options.timer);
            }
        });
    }

    var DiorBootstrapAlert = {
        fire: function (arg1, arg2, arg3) {
            var options = {};
            if (typeof arg1 === 'object' && arg1 !== null) {
                options = arg1;
            } else if (typeof arg1 === 'string') {
                options.title = arg1;
                if (typeof arg2 === 'string') options.text = arg2;
                if (typeof arg3 === 'string') options.icon = arg3;
            }

            if (options.toast) {
                return fireToast(options);
            }
            return fireModal(options);
        },

        close: function () {
            if (currentModalInstance) {
                try { currentModalInstance.hide(); } catch (e) {}
            }
            var toasts = document.querySelectorAll('.dior-bs-toast');
            toasts.forEach(function (t) {
                if (t.parentNode) t.parentNode.removeChild(t);
            });
        },

        isVisible: function () {
            return !!(currentModalElement && currentModalElement.classList.contains('show'));
        },

        getPopup: function () {
            return currentModalElement ? currentModalElement.querySelector('.modal-content') : null;
        },

        showLoading: function () {
            if (currentModalElement) {
                var body = currentModalElement.querySelector('.modal-body');
                if (body) {
                    var spinner = document.createElement('div');
                    spinner.className = 'd-flex justify-content-center my-3';
                    spinner.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
                    body.appendChild(spinner);
                }
            }
        }
    };

    // Maximize Native Bootstrap 5 Component Initializations
    function initBootstrapComponents(root) {
        root = root || document;
        if (!window.bootstrap) return;

        // Tooltips
        if (window.bootstrap.Tooltip) {
            try {
                var tooltips = [].slice.call(root.querySelectorAll('[data-bs-toggle="tooltip"], button[title]:not([title=""]):not(.va-page-btn):not(.dior-page-btn)'));
                tooltips.forEach(function (el) {
                    if (!el._bsTooltip) {
                        el._bsTooltip = new window.bootstrap.Tooltip(el, { trigger: 'hover' });
                    }
                });
            } catch (e) {}
        }

        // Popovers
        if (window.bootstrap.Popover) {
            try {
                var popovers = [].slice.call(root.querySelectorAll('[data-bs-toggle="popover"]'));
                popovers.forEach(function (el) {
                    if (!el._bsPopover) {
                        el._bsPopover = new window.bootstrap.Popover(el);
                    }
                });
            } catch (e) {}
        }

        // Dropdowns
        if (window.bootstrap.Dropdown) {
            try {
                var dropdowns = [].slice.call(root.querySelectorAll('[data-bs-toggle="dropdown"]'));
                dropdowns.forEach(function (el) {
                    if (!el._bsDropdown) {
                        el._bsDropdown = new window.bootstrap.Dropdown(el);
                    }
                });
            } catch (e) {}
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initBootstrapComponents(document);
        });
    } else {
        initBootstrapComponents(document);
    }

    // Dynamic element observer for newly added Bootstrap widgets
    if (window.MutationObserver) {
        var bsObserver = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.addedNodes && m.addedNodes.length) {
                    m.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) {
                            initBootstrapComponents(node);
                        }
                    });
                }
            });
        });
        bsObserver.observe(document.body, { childList: true, subtree: true });
    }

    // Global Export: window.Swal & window.bootstrapAlert
    window.Swal = DiorBootstrapAlert;
    window.bootstrapAlert = DiorBootstrapAlert;

    // Override browser default window.alert with Smart Bootstrap SweetAlert Modal
    window.alert = function (message) {
        var str = String(message !== undefined && message !== null ? message : '');
        var lower = str.toLowerCase();
        var icon = 'info';
        var title = 'Notice';

        if (lower.indexOf('success') !== -1 || lower.indexOf('saved') !== -1 || lower.indexOf('updated') !== -1 || lower.indexOf('submitted') !== -1 || lower.indexOf('delivered') !== -1 || lower.indexOf('published') !== -1) {
            icon = 'success';
            title = 'Success';
        } else if (lower.indexOf('error') !== -1 || lower.indexOf('failed') !== -1 || lower.indexOf('connection') !== -1 || lower.indexOf('could not') !== -1) {
            icon = 'error';
            title = 'Error';
        } else if (lower.indexOf('please') !== -1 || lower.indexOf('warning') !== -1 || lower.indexOf('required') !== -1 || lower.indexOf('exceeds') !== -1 || lower.indexOf('cannot') !== -1 || lower.indexOf('select') !== -1) {
            icon = 'warning';
            title = 'Attention';
        }

        return DiorBootstrapAlert.fire({
            title: title,
            text: str,
            icon: icon,
            confirmButtonText: 'OK',
            confirmButtonColor: icon === 'error' ? '#EF4444' : (icon === 'warning' ? '#F59E0B' : '#00346C')
        });
    };

    // Helper for Bootstrap Confirmation Dialogs
    window.diorConfirm = function (message, onConfirm, onCancel) {
        return DiorBootstrapAlert.fire({
            title: 'Are you sure?',
            text: String(message || 'Please confirm this action.'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#64748B',
            confirmButtonText: 'Yes, proceed',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (result.isConfirmed) {
                if (typeof onConfirm === 'function') onConfirm();
            } else {
                if (typeof onCancel === 'function') onCancel();
            }
            return result;
        });
    };

    // Override browser default window.confirm to avoid default popup
    var origConfirm = window.confirm;
    window.confirm = function (message) {
        // Trigger non-blocking Bootstrap SweetAlert confirm
        window.diorConfirm(message);
        // Return false for synchronous flow safety so default destructive form submit or deletion doesn't happen silently
        return false;
    };

    if (window.bootstrap) {
        window.bootstrap.Swal = DiorBootstrapAlert;
        window.bootstrap.Alert = DiorBootstrapAlert;
        window.bootstrap.initComponents = initBootstrapComponents;
    }
})();
