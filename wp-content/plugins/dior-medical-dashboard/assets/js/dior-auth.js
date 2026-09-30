/**
 * Dior Medical - Patient Authentication JS (Login & Registration)
 */

document.addEventListener('DOMContentLoaded', function() {

    // -----------------------------------------------------------------
    // 0. PASSWORD TOGGLE BUTTONS
    // -----------------------------------------------------------------
    const eyeSvg = '<svg class="dior-eye-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeSlashSvg = '<svg class="dior-eye-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

    document.querySelectorAll('.dior-pwd-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const wrap = btn.closest('.dior-input-pwd-wrap') || btn.parentElement;
            if (!wrap) return;
            const input = wrap.querySelector('input[type="password"], input[type="text"]');
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.innerHTML = eyeSlashSvg;
                btn.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                btn.innerHTML = eyeSvg;
                btn.setAttribute('aria-label', 'Show password');
            }
        });
    });

    // -----------------------------------------------------------------
    // 1. LOGIN FORM SUBMISSION (AJAX)
    // -----------------------------------------------------------------
    const loginForm = document.getElementById('dior-login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (typeof dior_auth_vars === 'undefined') {
                console.error('dior_auth_vars is missing');
                return;
            }

            const msgBox = document.getElementById('dior-login-msg');
            const submitBtn = document.getElementById('dior-login-submit-btn');
            const origText = submitBtn ? submitBtn.innerHTML : 'Submit';

            if (msgBox) {
                msgBox.style.display = 'none';
                msgBox.className = 'dior-auth-msg';
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Signing in...</span>';
            }

            const formData = new FormData(this);
            formData.append('action', 'dior_ajax_login');
            formData.append('nonce', dior_auth_vars.auth_nonce);

            // Pass query param redirect_to if present
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('redirect_to') && !formData.has('redirect_to')) {
                formData.append('redirect_to', urlParams.get('redirect_to'));
            }

            fetch(dior_auth_vars.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }

                if (res.success) {
                    if (msgBox) {
                        msgBox.classList.add('success');
                        msgBox.innerHTML = res.data.message || 'Login successful! Redirecting...';
                        msgBox.style.display = 'block';
                    }

                    setTimeout(function() {
                        window.location.href = res.data.redirect || dior_auth_vars.home_url;
                    }, 800);
                } else {
                    if (msgBox) {
                        msgBox.classList.add('error');
                        msgBox.innerHTML = res.data && res.data.message ? res.data.message : 'Invalid credentials. Please try again.';
                        msgBox.style.display = 'block';
                    }
                }
            })
            .catch(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }
                if (msgBox) {
                    msgBox.classList.add('error');
                    msgBox.innerHTML = 'Network error. Please try again.';
                    msgBox.style.display = 'block';
                }
            });
        });
    }

    // -----------------------------------------------------------------
    // 2. REGISTRATION FORM SUBMISSION (AJAX)
    // -----------------------------------------------------------------
    const regForm = document.getElementById('dior-register-form');
    if (regForm) {
        regForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (typeof dior_auth_vars === 'undefined') {
                console.error('dior_auth_vars is missing');
                return;
            }

            const msgBox = document.getElementById('dior-register-msg');
            const submitBtn = document.getElementById('dior-register-submit-btn');
            const origText = submitBtn ? submitBtn.innerHTML : 'Submit';

            if (msgBox) {
                msgBox.style.display = 'none';
                msgBox.className = 'dior-auth-msg';
            }

            const pwdEl = document.getElementById('dior-reg-password');
            const confirmPwdEl = document.getElementById('dior-reg-confirm-password');
            const pwd = pwdEl ? pwdEl.value : '';
            const confirmPwd = confirmPwdEl ? confirmPwdEl.value : '';
            const eligibility = regForm.querySelector('input[name="eligibility_check"]:checked');

            if (!eligibility || eligibility.value !== 'yes') {
                if (msgBox) {
                    msgBox.classList.add('error');
                    msgBox.innerHTML = 'You must confirm that you are 18+ years of age to register.';
                    msgBox.style.display = 'block';
                }
                return;
            }

            const regDobEl = document.getElementById('dior-reg-dob');
            if (regDobEl) {
                const regDobVal = regDobEl.value.trim();
                if (!regDobVal) {
                    if (msgBox) {
                        msgBox.classList.add('error');
                        msgBox.innerHTML = 'Please enter your date of birth.';
                        msgBox.style.display = 'block';
                    }
                    regDobEl.focus();
                    return;
                }
                const bDate = new Date(regDobVal);
                const now = new Date();
                let age = now.getFullYear() - bDate.getFullYear();
                const dm = now.getMonth() - bDate.getMonth();
                if (dm < 0 || (dm === 0 && now.getDate() < bDate.getDate())) {
                    age--;
                }

                if (bDate > now) {
                    if (msgBox) {
                        msgBox.classList.add('error');
                        msgBox.innerHTML = 'Invalid Date of Birth: Birth date cannot be in the future.';
                        msgBox.style.display = 'block';
                    }
                    regDobEl.focus();
                    return;
                }

                if (age < 18) {
                    if (msgBox) {
                        msgBox.classList.add('error');
                        msgBox.innerHTML = 'You must be at least 18 years of age to register for Dior Medical adult telehealth care (Calculated age: ' + Math.max(0, age) + ' years).';
                        msgBox.style.display = 'block';
                    }
                    regDobEl.focus();
                    return;
                }
            }

            if (pwd.length < 6) {
                if (msgBox) {
                    msgBox.classList.add('error');
                    msgBox.innerHTML = 'Password must be at least 6 characters long.';
                    msgBox.style.display = 'block';
                }
                return;
            }

            if (pwd !== confirmPwd) {
                if (msgBox) {
                    msgBox.classList.add('error');
                    msgBox.innerHTML = 'Passwords do not match. Please re-enter.';
                    msgBox.style.display = 'block';
                }
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Creating account...</span>';
            }

            const formData = new FormData(this);
            formData.append('action', 'dior_ajax_register');
            formData.append('nonce', dior_auth_vars.auth_nonce);

            fetch(dior_auth_vars.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }

                if (res.success) {
                    if (msgBox) {
                        msgBox.classList.add('success');
                        msgBox.innerHTML = res.data.message || 'Account created successfully! Redirecting...';
                        msgBox.style.display = 'block';
                    }

                    setTimeout(function() {
                        window.location.href = res.data.redirect || dior_auth_vars.home_url;
                    }, 900);
                } else {
                    if (msgBox) {
                        msgBox.classList.add('error');
                        msgBox.innerHTML = res.data && res.data.message ? res.data.message : 'Error creating account.';
                        msgBox.style.display = 'block';
                    }
                }
            })
            .catch(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }
                if (msgBox) {
                    msgBox.classList.add('error');
                    msgBox.innerHTML = 'Network error. Please try again.';
                    msgBox.style.display = 'block';
                }
            });
        });
    }

    // -----------------------------------------------------------------
    // 3. LOST PASSWORD FORM SUBMISSION (AJAX)
    // -----------------------------------------------------------------
    const lostForm = document.getElementById('dior-lost-password-form');
    if (lostForm) {
        lostForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (typeof dior_auth_vars === 'undefined') {
                console.error('dior_auth_vars is missing');
                return;
            }

            const msgBox = document.getElementById('dior-lost-msg');
            const submitBtn = document.getElementById('dior-lost-submit-btn');
            const origText = submitBtn ? submitBtn.innerHTML : 'Submit';
            const userLoginInput = document.getElementById('dior-lost-user-login');

            if (!userLoginInput || !userLoginInput.value.trim()) {
                if (msgBox) {
                    msgBox.classList.add('error');
                    msgBox.innerHTML = 'Please enter your email address or username.';
                    msgBox.style.display = 'block';
                }
                return;
            }

            if (msgBox) {
                msgBox.style.display = 'none';
                msgBox.className = 'dior-auth-msg';
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Sending instructions...</span>';
            }

            const formData = new FormData(this);
            formData.append('action', 'dior_ajax_lost_password');
            formData.append('nonce', dior_auth_vars.auth_nonce);

            fetch(dior_auth_vars.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }

                if (res.success) {
                    if (msgBox) {
                        msgBox.classList.add('success');
                        msgBox.innerHTML = res.data && res.data.message ? res.data.message : 'Reset instructions sent!';
                        msgBox.style.display = 'block';
                    }
                    lostForm.reset();
                } else {
                    if (msgBox) {
                        msgBox.classList.add('error');
                        msgBox.innerHTML = res.data && res.data.message ? res.data.message : 'Error sending reset email. Please try again.';
                        msgBox.style.display = 'block';
                    }
                }
            })
            .catch(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }
                if (msgBox) {
                    msgBox.classList.add('error');
                    msgBox.innerHTML = 'Network error. Please try again.';
                    msgBox.style.display = 'block';
                }
            });
        });
    }

    // -----------------------------------------------------------------
    // 4. RESET PASSWORD FORM SUBMISSION (AJAX)
    // -----------------------------------------------------------------
    const resetForm = document.getElementById('dior-reset-password-form');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (typeof dior_auth_vars === 'undefined') {
                console.error('dior_auth_vars is missing');
                return;
            }

            const msgBox = document.getElementById('dior-reset-msg');
            const submitBtn = document.getElementById('dior-reset-submit-btn');
            const origText = submitBtn ? submitBtn.innerHTML : 'Submit';

            const newPwdEl = document.getElementById('dior-new-password');
            const confirmPwdEl = document.getElementById('dior-confirm-password');
            const newPwd = newPwdEl ? newPwdEl.value : '';
            const confirmPwd = confirmPwdEl ? confirmPwdEl.value : '';

            if (msgBox) {
                msgBox.style.display = 'none';
                msgBox.className = 'dior-auth-msg';
            }

            if (newPwd.length < 6) {
                if (msgBox) {
                    msgBox.classList.add('error');
                    msgBox.innerHTML = 'Password must be at least 6 characters long.';
                    msgBox.style.display = 'block';
                }
                return;
            }

            if (newPwd !== confirmPwd) {
                if (msgBox) {
                    msgBox.classList.add('error');
                    msgBox.innerHTML = 'Passwords do not match. Please re-enter.';
                    msgBox.style.display = 'block';
                }
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Updating password...</span>';
            }

            const formData = new FormData(this);
            formData.append('action', 'dior_ajax_reset_password');
            formData.append('nonce', dior_auth_vars.auth_nonce);

            fetch(dior_auth_vars.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }

                if (res.success) {
                    if (msgBox) {
                        msgBox.classList.add('success');
                        msgBox.innerHTML = res.data && res.data.message ? res.data.message : 'Password reset successful! Redirecting to login...';
                        msgBox.style.display = 'block';
                    }

                    setTimeout(function() {
                        window.location.href = res.data.redirect || (dior_auth_vars.login_url + '?reset=success');
                    }, 1200);
                } else {
                    if (msgBox) {
                        msgBox.classList.add('error');
                        msgBox.innerHTML = res.data && res.data.message ? res.data.message : 'Error resetting password. Link may have expired.';
                        msgBox.style.display = 'block';
                    }
                }
            })
            .catch(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }
                if (msgBox) {
                    msgBox.classList.add('error');
                    msgBox.innerHTML = 'Network error. Please try again.';
                    msgBox.style.display = 'block';
                }
            });
        });
    }

});
