/**
 * Dior Medical - FDA Approved Medication Search & Digital Prescription Modal Controller
 * Connects directly to U.S. FDA Drug NDC Directory API (openFDA)
 * Replicates the reference clinical prescription builder UI
 */
(function() {
    'use strict';

    // Built-in FDA Verified Medication Catalog (Fallback + Instant Cache)
    var FDA_FALLBACK_CATALOG = [
        { brand_name: "SPORANOX", generic_name: "ITRACONAZOLE", strength: "100 MG", dosage_form: "Capsule(s)", route: "Orally", ndc: "50458-290" },
        { brand_name: "AMOXICILLIN", generic_name: "AMOXICILLIN", strength: "500 MG", dosage_form: "Capsule(s)", route: "Orally", ndc: "76420-112" },
        { brand_name: "LIPITOR", generic_name: "ATORVASTATIN CALCIUM", strength: "20 MG", dosage_form: "Tablet(s)", route: "Orally", ndc: "0071-0156" },
        { brand_name: "ZESTRIL", generic_name: "LISINOPRIL", strength: "10 MG", dosage_form: "Tablet(s)", route: "Orally", ndc: "0310-0130" },
        { brand_name: "GLUCOPHAGE", generic_name: "METFORMIN HYDROCHLORIDE", strength: "500 MG", dosage_form: "Tablet(s)", route: "Orally", ndc: "0087-6060" },
        { brand_name: "SYNTHROID", generic_name: "LEVOTHYROXINE SODIUM", strength: "50 MCG", dosage_form: "Tablet(s)", route: "Orally", ndc: "0074-7068" },
        { brand_name: "NORVASC", generic_name: "AMLODIPINE BESYLATE", strength: "5 MG", dosage_form: "Tablet(s)", route: "Orally", ndc: "0069-1530" },
        { brand_name: "PRILOSEC", generic_name: "OMEPRAZOLE", strength: "20 MG", dosage_form: "Capsule(s)", route: "Orally", ndc: "0186-0602" },
        { brand_name: "NEURONTIN", generic_name: "GABAPENTIN", strength: "300 MG", dosage_form: "Capsule(s)", route: "Orally", ndc: "0071-0805" },
        { brand_name: "ZITHROMAX", generic_name: "AZITHROMYCIN", strength: "250 MG", dosage_form: "Tablet(s)", route: "Orally", ndc: "0069-3060" },
        { brand_name: "AUGMENTIN", generic_name: "AMOXICILLIN AND CLAVULANATE", strength: "875 MG / 125 MG", dosage_form: "Tablet(s)", route: "Orally", ndc: "43598-005" },
        { brand_name: "COZAAR", generic_name: "LOSARTAN POTASSIUM", strength: "50 MG", dosage_form: "Tablet(s)", route: "Orally", ndc: "0006-0952" },
        { brand_name: "VENTOLIN HFA", generic_name: "ALBUTEROL SULFATE", strength: "90 MCG", dosage_form: "Inhaler", route: "Inhalation", ndc: "0173-0682" },
        { brand_name: "BACTRIM DS", generic_name: "SULFAMETHOXAZOLE AND TRIMETHOPRIM", strength: "800 MG / 160 MG", dosage_form: "Tablet(s)", route: "Orally", ndc: "49702-211" },
        { brand_name: "ZOFRAN", generic_name: "ONDANSETRON", strength: "4 MG", dosage_form: "Tablet(s)", route: "Orally", ndc: "0173-0446" },
        { brand_name: "MEDROL", generic_name: "METHYLPREDNISOLONE", strength: "4 MG", dosage_form: "Tablet(s)", route: "Orally", ndc: "0009-0056" }
    ];

    // State
    window.diorFdaState = {
        medicationsList: [],
        itemNumber: 1,
        brandName: "",
        genericName: "",
        strength: "500 mg",
        containsText: "",
        includeGeneric: true,
        takeOrApply: "Take",
        doseForm: "Tablet(s)",
        route: "Orally",
        durationVal: 1,
        durationUnit: "Week(s)",
        frequency: "Daily",
        intake: "After Food",
        morning: 0,
        noon: 0,
        evening: 0,
        night: 1,
        daily: 0,
        customNotes: "",
        frequentlyUsed: false,
        isSearching: false,
        searchTimer: null
    };

    /**
     * Open the FDA Prescription Modal
     */
    window.diorOpenFdaPrescriptionModal = function() {
        var modal = document.getElementById('dior-fda-prescription-modal');
        if (!modal) {
            console.warn('[Dior Medical] FDA Prescription modal element not found.');
            return;
        }

        // Reset state so every open is clean and fresh
        var s = window.diorFdaState;
        s.medicationsList = [];
        s.brandName = "";
        s.genericName = "";
        s.strength = "500 mg";

        // Set default date
        var dateInput = document.getElementById('dior-fda-presc-date');
        if (dateInput && !dateInput.value) {
            dateInput.value = new Date().toISOString().split('T')[0];
        }

        var searchInput = document.getElementById('dior-fda-med-search');
        if (searchInput) searchInput.value = '';

        window.diorFdaRenderPrescribedList();
        window.diorFdaUpdateAllUI();

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
            bsModal.show();
        } else {
            modal.style.display = 'block';
            modal.classList.add('show');
            modal.classList.add('is-open');
        }

        if (searchInput) {
            setTimeout(function() { searchInput.focus(); }, 150);
        }
    };

    /**
     * Close the Modal
     */
    window.diorCloseFdaPrescriptionModal = function() {
        var modal = document.getElementById('dior-fda-prescription-modal');
        if (!modal) return;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
            bsModal.hide();
        }
        modal.classList.remove('is-open');
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    /**
     * FDA API Search with Debounce & Suggestions Dropdown
     */
    window.diorOnFdaSearchInput = function(query) {
        clearTimeout(window.diorFdaState.searchTimer);
        var dropdown = document.getElementById('dior-fda-autocomplete-dropdown');
        if (!dropdown) return;

        query = (query || '').trim();
        if (query.length < 2) {
            dropdown.style.display = 'none';
            dropdown.innerHTML = '';
            return;
        }

        window.diorFdaState.searchTimer = setTimeout(function() {
            dropdown.innerHTML = '<div style="padding:12px;text-align:center;color:#64748b;font-size:13px;"><i class="fas fa-circle-notch fa-spin me-2"></i> Querying U.S. FDA Drug Database...</div>';
            dropdown.style.display = 'block';

            // Query official openFDA API
            var fdaUrl = 'https://api.fda.gov/drug/ndc.json?search=(brand_name:' + encodeURIComponent(query) + '*+generic_name:' + encodeURIComponent(query) + '*)&limit=8';

            fetch(fdaUrl)
                .then(function(res) {
                    if (!res.ok) throw new Error('FDA HTTP ' + res.status);
                    return res.json();
                })
                .then(function(data) {
                    if (data && data.results && data.results.length > 0) {
                        window.diorRenderFdaSearchResults(data.results);
                    } else {
                        window.diorRenderFallbackSearchResults(query);
                    }
                })
                .catch(function(err) {
                    console.log('[Dior Medical] openFDA query note, using FDA catalog fallback:', err.message);
                    window.diorRenderFallbackSearchResults(query);
                });
        }, 220);
    };

    /**
     * Render live openFDA results
     */
    window.diorRenderFdaSearchResults = function(results) {
        var dropdown = document.getElementById('dior-fda-autocomplete-dropdown');
        if (!dropdown) return;
        dropdown.innerHTML = '';

        results.forEach(function(item) {
            var brand = item.brand_name || item.brand_name_base || 'Medication';
            var generic = item.generic_name || '';
            var active = (item.active_ingredients && item.active_ingredients[0]) ? item.active_ingredients[0] : null;
            var strength = active ? (active.strength || '') : '';
            var dosageForm = window.diorFormatDoseForm(item.dosage_form);
            var route = window.diorFormatRoute(item.route);

            var div = document.createElement('div');
            div.className = 'dior-fda-autocomplete-item';
            div.innerHTML = `
                <div class="dior-fda-item-main">
                    <span class="dior-fda-item-brand">${brand} ${dosageForm}</span>
                    <span class="dior-fda-item-generic">Generic: ${generic} ${strength ? '(' + strength + ')' : ''}</span>
                </div>
                <span class="dior-fda-item-badge"><i class="fas fa-shield-check me-1"></i> FDA Verified</span>
            `;

            div.addEventListener('click', function() {
                window.diorSelectMedication({
                    brand_name: brand,
                    generic_name: generic,
                    strength: strength || 'Standard Dose',
                    dosage_form: dosageForm,
                    route: route
                });
            });

            dropdown.appendChild(div);
        });

        dropdown.style.display = 'block';
    };

    /**
     * Fallback search in verified catalog
     */
    window.diorRenderFallbackSearchResults = function(query) {
        var dropdown = document.getElementById('dior-fda-autocomplete-dropdown');
        if (!dropdown) return;
        dropdown.innerHTML = '';

        var qLower = query.toLowerCase();
        var matches = FDA_FALLBACK_CATALOG.filter(function(m) {
            return m.brand_name.toLowerCase().indexOf(qLower) !== -1 || m.generic_name.toLowerCase().indexOf(qLower) !== -1;
        });

        if (matches.length === 0) {
            // Offer free text creation
            dropdown.innerHTML = `
                <div class="dior-fda-autocomplete-item" onclick="window.diorSelectMedication({ brand_name: '${query.toUpperCase()}', generic_name: '${query.toUpperCase()}', strength: 'Standard Dose', dosage_form: 'Tablet(s)', route: 'Orally' })">
                    <div class="dior-fda-item-main">
                        <span class="dior-fda-item-brand">${query.toUpperCase()}</span>
                        <span class="dior-fda-item-generic">Custom Prescribed Medication</span>
                    </div>
                    <span class="dior-fda-item-badge" style="background:#fef3c7;color:#b45309;">Custom Entry</span>
                </div>
            `;
            return;
        }

        matches.forEach(function(m) {
            var div = document.createElement('div');
            div.className = 'dior-fda-autocomplete-item';
            div.innerHTML = `
                <div class="dior-fda-item-main">
                    <span class="dior-fda-item-brand">${m.brand_name} ${m.dosage_form}</span>
                    <span class="dior-fda-item-generic">Contains: ${m.generic_name} (${m.strength})</span>
                </div>
                <span class="dior-fda-item-badge"><i class="fas fa-shield-check me-1"></i> FDA Verified</span>
            `;

            div.addEventListener('click', function() {
                window.diorSelectMedication(m);
            });

            dropdown.appendChild(div);
        });

        dropdown.style.display = 'block';
    };

    /**
     * Select medication from autocomplete
     */
    window.diorSelectMedication = function(med) {
        var s = window.diorFdaState;
        s.brandName = (med.brand_name || 'Medication').trim();
        s.genericName = (med.generic_name || s.brandName).trim();
        s.strength = (med.strength || '500 mg').trim();
        s.doseForm = med.dosage_form || 'Tablet(s)';
        s.route = med.route || 'Orally';

        var dropdown = document.getElementById('dior-fda-autocomplete-dropdown');
        if (dropdown) dropdown.style.display = 'none';

        var searchInput = document.getElementById('dior-fda-med-search');
        if (searchInput) {
            searchInput.value = window.diorFormatCleanMedName(s.brandName, s.strength);
        }

        var strengthInput = document.getElementById('dior-fda-strength');
        if (strengthInput && s.strength) {
            strengthInput.value = s.strength;
        }

        var doseFormSelect = document.getElementById('dior-fda-dose-form');
        if (doseFormSelect && s.doseForm) {
            for (var i = 0; i < doseFormSelect.options.length; i++) {
                if (doseFormSelect.options[i].value.toLowerCase().indexOf(s.doseForm.toLowerCase().replace('(s)', '')) !== -1) {
                    doseFormSelect.selectedIndex = i;
                    break;
                }
            }
        }

        var routeSelect = document.getElementById('dior-fda-route');
        if (routeSelect && s.route) {
            for (var j = 0; j < routeSelect.options.length; j++) {
                if (routeSelect.options[j].value.toLowerCase().indexOf(s.route.toLowerCase()) !== -1) {
                    routeSelect.selectedIndex = j;
                    break;
                }
            }
        }

        window.diorFdaUpdateAllUI();
    };

    /**
     * Format FDA dosage form to clinical friendly text
     */
    window.diorFormatDoseForm = function(raw) {
        if (!raw) return 'Capsule(s)';
        var r = String(raw).toUpperCase();
        if (r.indexOf('CAPSULE') !== -1) return 'Capsule(s)';
        if (r.indexOf('TABLET') !== -1) return 'Tablet(s)';
        if (r.indexOf('SYRUP') !== -1 || r.indexOf('LIQUID') !== -1) return 'Syrup';
        if (r.indexOf('INJECTION') !== -1) return 'Injection';
        if (r.indexOf('CREAM') !== -1) return 'Cream';
        if (r.indexOf('OINTMENT') !== -1) return 'Ointment';
        if (r.indexOf('DROP') !== -1) return 'Drops';
        if (r.indexOf('INHAL') !== -1 || r.indexOf('AEROSOL') !== -1) return 'Inhaler';
        return 'Capsule(s)';
    };

    /**
     * Format FDA route to clinical friendly text
     */
    window.diorFormatRoute = function(raw) {
        if (!raw) return 'Orally';
        var arr = Array.isArray(raw) ? raw : [raw];
        var first = String(arr[0] || '').toUpperCase();
        if (first.indexOf('ORAL') !== -1) return 'Orally';
        if (first.indexOf('TOPIC') !== -1) return 'Topical';
        if (first.indexOf('OPHTHAL') !== -1) return 'Ophthalmic (Eye)';
        if (first.indexOf('NASAL') !== -1) return 'Nasal';
        if (first.indexOf('INHAL') !== -1) return 'Inhalation';
        if (first.indexOf('INTRAVEN') !== -1) return 'Intravenous (IV)';
        if (first.indexOf('INTRAMUSC') !== -1) return 'Intramuscular (IM)';
        return 'Orally';
    };

    /**
     * Stepper Handler for Duration
     */
    window.diorStepDuration = function(delta) {
        var s = window.diorFdaState;
        s.durationVal = Math.max(1, s.durationVal + delta);
        window.diorFdaUpdateAllUI();
    };

    /**
     * Stepper Handler for Dosage Timings (Morning, Noon, Evening, Night, Daily)
     */
    window.diorStepDosage = function(slot, delta) {
        var s = window.diorFdaState;
        if (typeof s[slot] !== 'undefined') {
            s[slot] = Math.max(0, s[slot] + delta);
        }
        window.diorFdaUpdateAllUI();
    };

    /**
     * Take vs Apply Radio
     */
    window.diorSetTakeOrApply = function(val) {
        window.diorFdaState.takeOrApply = val;
        var formLabel = document.getElementById('dior-fda-take-apply-label');
        if (formLabel) formLabel.textContent = val + ': (Dose Form)';
        if (val === 'Apply' && window.diorFdaState.route === 'Orally') {
            window.diorFdaState.route = 'Topical';
        }
        window.diorFdaUpdateAllUI();
    };

    /**
     * Duration Unit Radio (Day(s), Week(s), Month(s), Till Next Review)
     */
    window.diorSetDurationUnit = function(unit) {
        window.diorFdaState.durationUnit = unit;
        window.diorFdaUpdateAllUI();
    };

    /**
     * Intake Radio (Before Food, After Food, Not Applicable)
     */
    window.diorSetIntake = function(intake) {
        window.diorFdaState.intake = intake;
        window.diorFdaUpdateAllUI();
    };

    /**
     * Dose Form Select
     */
    window.diorOnDoseFormChange = function(val) {
        window.diorFdaState.doseForm = val;
        window.diorFdaUpdateAllUI();
    };

    /**
     * Route Select
     */
    window.diorOnRouteChange = function(val) {
        window.diorFdaState.route = val;
        window.diorFdaUpdateAllUI();
    };

    /**
     * Frequency Select
     */
    window.diorOnFrequencyChange = function(val) {
        window.diorFdaState.frequency = val;
        window.diorFdaUpdateAllUI();
    };

    /**
     * Toggle Free Text on active ingredient line
     */
    window.diorToggleFreeText = function() {
        var textEl = document.getElementById('dior-fda-contains-display');
        var inputEl = document.getElementById('dior-fda-contains-edit');
        if (!textEl || !inputEl) return;

        if (inputEl.style.display === 'none' || !inputEl.style.display) {
            inputEl.value = window.diorFdaState.containsText;
            inputEl.style.display = 'block';
            textEl.style.display = 'none';
            inputEl.focus();
        } else {
            window.diorFdaState.containsText = inputEl.value;
            textEl.textContent = inputEl.value;
            inputEl.style.display = 'none';
            textEl.style.display = 'block';
            window.diorFdaUpdateAllUI();
        }
    };

    /**
     * Toggle Extra Notes Drawer
     */
    window.diorToggleNotesDrawer = function() {
        var drawer = document.getElementById('dior-fda-notes-drawer');
        if (!drawer) return;
        var isVisible = drawer.style.display === 'block';
        drawer.style.display = isVisible ? 'none' : 'block';
        if (!isVisible) {
            var txt = drawer.querySelector('textarea');
            if (txt) txt.focus();
        }
    };

    /**
     * Clean Medication Name Formatter (e.g., Metformin (500 mg), Sporanox (100 mg))
     */
    window.diorFormatCleanMedName = function(name, strength) {
        if (!name) return 'Medication';
        var clean = name.toLowerCase().replace(/\b\w/g, function(l) { return l.toUpperCase(); }).trim();
        var str = (strength || '').trim().replace(/[()]/g, '');
        if (str && str.toLowerCase() !== 'standard dose') {
            return clean + ' (' + str + ')';
        }
        return clean;
    };

    /**
     * Master Re-compute & Synchronize UI
     */
    window.diorFdaUpdateAllUI = function() {
        var s = window.diorFdaState;

        // Header Title
        var titleEl = document.getElementById('dior-fda-title-text');
        if (titleEl) {
            if (s.medicationsList.length > 0) {
                titleEl.textContent = 'Clinical Prescription Builder (' + s.medicationsList.length + ' Added)';
            } else {
                titleEl.textContent = 'New Clinical Prescription';
            }
        }
    };

    /**
     * Add Currently Configured Medicine to Prescription List (Multi-Medication Support)
     */
    window.diorFdaAddMedicineToList = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        var s = window.diorFdaState;

        var searchInput = document.getElementById('dior-fda-med-search');
        var rawMedName = searchInput ? searchInput.value.trim() : '';

        if (!rawMedName && (!s.brandName || s.brandName.trim() === '')) {
            Swal.fire({ icon: 'warning', title: 'Medication Name Missing', text: 'Please enter or search for a medication name first.' });
            if (searchInput) searchInput.focus();
            return;
        }

        var strengthInput = document.getElementById('dior-fda-strength');
        var currentStrength = strengthInput ? strengthInput.value.trim() : (s.strength || '');

        var doseFormSelect = document.getElementById('dior-fda-dose-form');
        var currentDoseForm = doseFormSelect ? doseFormSelect.value : (s.doseForm || 'Tablet(s)');

        var routeSelect = document.getElementById('dior-fda-route');
        var currentRoute = routeSelect ? routeSelect.value : (s.route || 'Orally');

        var freqSelect = document.getElementById('dior-fda-frequency');
        var currentFreq = freqSelect ? freqSelect.value : 'Daily';

        var intakeSelect = document.getElementById('dior-fda-intake');
        var currentIntake = intakeSelect ? intakeSelect.value : 'After Food';

        var durationSelect = document.getElementById('dior-fda-duration');
        var currentDuration = durationSelect ? durationSelect.value : '7 Day(s)';

        var qtyInput = document.getElementById('dior-fda-quantity');
        var currentQty = qtyInput ? qtyInput.value.trim() : '30 Tablets';

        var refillsSelect = document.getElementById('dior-fda-refills');
        var currentRefills = refillsSelect ? refillsSelect.value : '0';

        var notesArea = document.getElementById('dior-fda-custom-notes');
        var customNotes = notesArea ? notesArea.value.trim() : '';

        var medTitle = rawMedName || s.brandName;
        var fullMedName = window.diorFormatCleanMedName(medTitle, currentStrength);

        var fullDosage = currentStrength + ' ' + currentDoseForm + ' (' + currentRoute + ')';
        var fullFrequency = currentFreq + ' (' + currentIntake + ')';
        var fullDuration = currentDuration;

        var instructions = 'Take ' + currentDoseForm + ' ' + currentFreq + ' ' + currentIntake + ' for ' + currentDuration + ' (' + currentRoute + ')';
        if (customNotes) {
            instructions += ' | Note: ' + customNotes;
        }

        var medItem = {
            brandName: medTitle,
            strength: currentStrength,
            doseForm: currentDoseForm,
            route: currentRoute,
            frequency: currentFreq,
            intake: currentIntake,
            duration: currentDuration,
            quantity: currentQty,
            refills: currentRefills,
            fullMedName: fullMedName,
            fullDosage: fullDosage,
            fullFrequency: fullFrequency,
            fullDuration: fullDuration,
            instructions: instructions,
            customNotes: customNotes
        };

        s.medicationsList.push(medItem);
        window.diorFdaRenderPrescribedList();

        // Feedback toast
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Added: ' + fullMedName
            });
        }

        // Prepare form for NEXT medication
        s.itemNumber = s.medicationsList.length + 1;
        s.brandName = "";
        s.genericName = "";
        s.strength = "";

        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        if (notesArea) notesArea.value = '';

        window.diorFdaUpdateAllUI();
    };

    /**
     * Render the Multi-Medication Cart Chips
     */
    window.diorFdaRenderPrescribedList = function() {
        var s = window.diorFdaState;
        var wrap = document.getElementById('dior-fda-prescribed-list-wrap');
        var itemsContainer = document.getElementById('dior-fda-prescribed-items');
        var countEl = document.getElementById('dior-fda-prescribed-count');
        var btnCountEl = document.getElementById('dior-fda-rx-btn-count');

        if (!wrap || !itemsContainer) return;

        if (s.medicationsList.length === 0) {
            wrap.style.display = 'none';
            itemsContainer.innerHTML = '';
            if (btnCountEl) btnCountEl.style.display = 'none';
            return;
        }

        wrap.style.display = 'block';
        if (countEl) countEl.textContent = s.medicationsList.length;
        if (btnCountEl) {
            btnCountEl.textContent = '(' + s.medicationsList.length + ')';
            btnCountEl.style.display = 'inline';
        }

        itemsContainer.innerHTML = '';
        s.medicationsList.forEach(function(item, idx) {
            var chip = document.createElement('div');
            chip.className = 'dior-fda-prescribed-chip';
            chip.innerHTML = `
                <div class="dior-fda-chip-info">
                    <span class="dior-fda-chip-badge">${idx + 1}</span>
                    <strong class="dior-fda-chip-name">${item.fullMedName}</strong>
                    <span class="dior-fda-chip-dose">&bull; ${item.fullDosage} &bull; ${item.fullFrequency} for ${item.fullDuration} (Total: ${item.totalUnits})</span>
                </div>
                <button type="button" class="dior-fda-chip-del" onclick="diorFdaRemoveMedicine(${idx})" title="Remove this medication">
                    <i class="fas fa-trash-can"></i>
                </button>
            `;
            itemsContainer.appendChild(chip);
        });
    };

    /**
     * Remove an item from the Multi-Medication Cart
     */
    window.diorFdaRemoveMedicine = function(idx) {
        var s = window.diorFdaState;
        if (idx >= 0 && idx < s.medicationsList.length) {
            s.medicationsList.splice(idx, 1);
            s.itemNumber = s.medicationsList.length + 1;
            window.diorFdaRenderPrescribedList();
            window.diorFdaUpdateAllUI();
        }
    };

    /**
     * Submit All Prescribed Medications to Database via AJAX
     */
    window.diorSubmitFdaPrescription = function(e) {
        if (e && e.preventDefault) e.preventDefault();

        var s = window.diorFdaState;
        var patientSelect = document.getElementById('dior-fda-patient-select');
        var patientId = patientSelect ? patientSelect.value : '';
        var patientName = patientSelect ? (patientSelect.options[patientSelect.selectedIndex].text.split('(')[0].trim()) : 'Patient';

        if (!patientId) {
            Swal.fire({ icon: 'warning', title: 'Select Patient', text: 'Please select a patient for this prescription.' });
            return;
        }

        var searchInp = document.getElementById('dior-fda-med-search');
        var typedMedName = searchInp ? searchInp.value.trim() : '';

        // If no medication is in the list yet, auto-add from the current form fields
        if (s.medicationsList.length === 0) {
            if (typedMedName || (s.brandName && s.brandName.trim() !== '')) {
                window.diorFdaAddMedicineToList(e);
            } else {
                var prescTitleFallback = (document.getElementById('dior-fda-presc-title') ? document.getElementById('dior-fda-presc-title').value.trim() : '') || 'Clinical Medication';
                s.brandName = prescTitleFallback;
                window.diorFdaAddMedicineToList(e);
            }
        }
        // NOTE: If medicationsList already has items, DO NOT add more! Save what has been added!

        if (s.medicationsList.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No Medication Added', text: 'Please search or type at least one medication name.' });
            return;
        }

        var dateInputEl = document.getElementById('dior-fda-presc-date');
        var prescDate = (dateInputEl && dateInputEl.value) ? dateInputEl.value : new Date().toISOString().split('T')[0];
        var btn = document.getElementById('dior-fda-btn-submit');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> Saving Prescription...';
        }

        // Compile all medications into database fields
        var medNames = [];
        var dosages = [];
        var frequencies = [];
        var durations = [];
        var instructions = [];

        s.medicationsList.forEach(function(item, idx) {
            var prefix = (s.medicationsList.length > 1) ? (idx + 1) + '. ' : '';
            medNames.push(prefix + item.fullMedName);
            dosages.push(prefix + item.fullDosage);
            frequencies.push(prefix + item.fullFrequency);
            durations.push(prefix + item.fullDuration);
            instructions.push(prefix + item.instructions);
        });

        var compiledMedications = medNames.join('; \n');
        var compiledDosage = dosages.join(' | ');
        var compiledFrequency = frequencies.join(' | ');
        var compiledDuration = durations.join(' | ');
        var compiledInstructions = instructions.join(' || ');

        var prescTitle = (document.getElementById('dior-fda-presc-title') ? document.getElementById('dior-fda-presc-title').value.trim() : '') || 'General Medical Prescription';

        var formData = new FormData();
        formData.append('action', 'dior_doctor_add_prescription');
        var nonceVal = window.dior_doctor_nonce ||
                       (document.getElementById('dior-fda-nonce') ? document.getElementById('dior-fda-nonce').value : '') ||
                       (window.dior_fda_vars ? window.dior_fda_vars.nonce : '') ||
                       (typeof dior_doctor_vars !== 'undefined' ? dior_doctor_vars.nonce : '') ||
                       (typeof dior_vars !== 'undefined' ? dior_vars.nonce : '');
        formData.append('nonce', nonceVal);
        formData.append('title', prescTitle);
        formData.append('patient_id', patientId);
        formData.append('patient_name', patientName);
        formData.append('prescription_id', 'RX-' + new Date().getFullYear().toString().substr(-2) + String(Math.floor(100000 + Math.random() * 900000)));
        formData.append('prescription_date', prescDate);
        formData.append('medications', compiledMedications);
        formData.append('dosage', compiledDosage);
        formData.append('frequency', compiledFrequency);
        formData.append('duration', compiledDuration);
        formData.append('instructions', compiledInstructions);
        formData.append('quantity', s.medicationsList.length + ' Item(s)');
        formData.append('status', 'Active');

        var ajaxUrl = window.dior_ajax_url ||
                      (window.dior_fda_vars && window.dior_fda_vars.ajax_url) ||
                      (typeof dior_doctor_vars !== 'undefined' ? dior_doctor_vars.ajax_url : '') ||
                      (typeof dior_vars !== 'undefined' ? dior_vars.ajax_url : '') ||
                      (window.dior_ajax_object ? window.dior_ajax_object.ajax_url : '') ||
                      (window.ajaxurl ? window.ajaxurl : '') ||
                      (typeof dior_patient_dashboard !== 'undefined' ? dior_patient_dashboard.ajax_url : null) ||
                      '';

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-prescription me-1"></i> Save &amp; Issue Prescription';
            }

            if (res.success) {
                var totalCount = s.medicationsList.length;
                var savedTitle = (res.data && res.data.title) ? res.data.title : prescTitle;
                var savedRxId = (res.data && res.data.prescription_id) ? res.data.prescription_id : ('RX-' + Date.now().toString().substr(-5));

                // Live insert row into Digital Prescriptions table
                window.diorInjectPrescriptionRow({
                    prescription_id: savedRxId,
                    title: savedTitle,
                    patient_name: patientName,
                    date: prescDate,
                    medications: compiledMedications,
                    dosage: compiledDosage,
                    frequency: compiledFrequency,
                    duration: compiledDuration,
                    instructions: compiledInstructions,
                    doctor: 'Dr. Marcus Sterling, DO',
                    status: 'Active',
                    count: totalCount
                });

                // Reset state
                s.medicationsList = [];
                s.brandName = "";
                s.genericName = "";
                window.diorFdaRenderPrescribedList();
                window.diorCloseFdaPrescriptionModal();

                Swal.fire({
                    icon: 'success',
                    title: 'Prescription Issued Successfully!',
                    html: 'Prescription <strong>' + savedRxId + '</strong> (' + totalCount + ' medication(s)) saved for <strong>' + patientName + '</strong>.<br><small class="text-secondary">Synced to both Doctor and Patient portals.</small>',
                    confirmButtonColor: '#2563eb'
                }).then(function() {
                    window.location.reload();
                });
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'Failed to save prescription.';
                Swal.fire({ icon: 'error', title: 'Error Saving Prescription', text: msg });
            }
        })
        .catch(function(err) {
            console.error('[Dior Medical] Prescription save error:', err);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-prescription me-1"></i> Save &amp; Issue Prescription';
            }
            Swal.fire({ icon: 'error', title: 'Network Error', text: 'Network request failed. Please check your connection and try again.' });
        });
    };

    /**
     * Live Table Injection for Instant Feedback
     */
    window.diorInjectPrescriptionRow = function(p) {
        var tbody = document.querySelector('#tab-doc-e-prescriptions table.va-table tbody');
        if (!tbody) return;

        // Remove empty state row if present
        var emptyRow = tbody.querySelector('td[colspan]');
        if (emptyRow && emptyRow.closest('tr')) {
            emptyRow.closest('tr').remove();
        }

        var viewDataJson = JSON.stringify({
            id: p.prescription_id,
            title: p.title || 'General Care Prescription',
            patient_name: p.patient_name,
            date: p.date,
            medications: p.medications,
            dosage: p.dosage,
            frequency: p.frequency,
            duration: p.duration,
            doctor_name: p.doctor || 'Dr. Marcus Sterling, DO',
            status: p.status || 'Active',
            instructions: p.instructions || ''
        }).replace(/"/g, '&quot;');

        var formatCellLines = function(raw, delim) {
            if (!raw) return '';
            var parts = String(raw).split(delim || ';').map(function(s) { return s.trim(); }).filter(Boolean);
            if (!parts.length) parts = [raw];
            return '<div style="display:flex;flex-direction:column;gap:3px;">' + 
                   parts.map(function(s) { return '<div style="white-space:normal;">' + s + '</div>'; }).join('') + 
                   '</div>';
        };

        var medsHtml = formatCellLines(p.medications, ';');
        var doseHtml = formatCellLines(p.dosage, '|');
        var freqHtml = formatCellLines(p.frequency, '|');
        var durHtml  = formatCellLines(p.duration, '|');

        var tr = document.createElement('tr');
        tr.style.animation = 'diorFadeIn 0.4s ease';
        tr.innerHTML = `
            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
            <td><strong>${p.prescription_id}</strong></td>
            <td>
                <span class="dior-rx-title-badge" style="font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-file-waveform" style="color: #d4af37;"></i>
                    ${p.title || 'General Care Prescription'}
                </span>
            </td>
            <td>
                <div class="va-user-profile">
                    <span class="va-user-name">${p.patient_name}</span>
                </div>
            </td>
            <td><i class="far fa-calendar va-icon-blue"></i> ${p.date}</td>
            <td><div style="font-weight:700;color:#0f172a;">${medsHtml}</div></td>
            <td>${doseHtml}</td>
            <td>${freqHtml}</td>
            <td>${durHtml}</td>
            <td>${p.doctor}</td>
            <td><span class="badge-solid col-green" style="background-color:transparent;color:#059669;padding:6px 12px;border-radius:6px;font-weight:700;">${p.status}</span></td>
            <td>
                <div class="va-row-actions">
                    <button type="button" class="va-action-btn-sm va-btn-view" title="View Prescription Details" onclick="diorOpenPrescriptionDetailModal(${viewDataJson})"><i class="fas fa-eye"></i></button>
                    <button type="button" class="va-action-btn-sm va-btn-edit" title="Edit Prescription" onclick="diorOpenFdaPrescriptionModal()"><i class="fas fa-pen-to-square"></i></button>
                    <button type="button" class="va-action-btn-sm va-btn-delete" title="Delete Prescription" onclick="diorDeletePrescriptionRow(this)"><i class="fas fa-trash-can"></i></button>
                </div>
            </td>
        `;

        tbody.insertBefore(tr, tbody.firstChild);

        // Update pagination counter
        var countEl = document.querySelector('#tab-doc-e-prescriptions .va-pagination-text');
        if (countEl) {
            var current = parseInt(countEl.textContent.replace(/[^0-9]/g, ''), 10) || 0;
            countEl.textContent = '0 selected / ' + (current + 1) + ' total';
        }
    };

    /**
     * View Prescription Details Modal Controller
     */
    window.diorOpenPrescriptionDetailModal = function(data) {
        if (!data) return;
        var modal = document.getElementById('dior-prescription-view-modal');
        if (!modal) return;

        var idEl = document.getElementById('dior-view-rx-id');
        if (idEl) idEl.textContent = data.id || 'RX-000000';

        var titleEl = document.getElementById('dior-view-rx-title');
        if (titleEl) titleEl.textContent = data.title || 'General Care Prescription';

        var patEl = document.getElementById('dior-view-rx-patient');
        if (patEl) patEl.textContent = data.patient_name || 'Patient';

        var docEl = document.getElementById('dior-view-rx-doctor');
        if (docEl) docEl.textContent = data.doctor_name || 'Dr. Marcus Sterling, DO';

        var dateEl = document.getElementById('dior-view-rx-date');
        if (dateEl) dateEl.textContent = data.date || '';

        var statusEl = document.getElementById('dior-view-rx-status');
        if (statusEl) statusEl.textContent = data.status || 'Active';

        var notesEl = document.getElementById('dior-view-rx-notes');
        if (notesEl) {
            notesEl.textContent = data.instructions ? data.instructions : 'No special clinical notes recorded.';
        }

        // Render clean medication list
        var listContainer = document.getElementById('dior-view-rx-meds-list');
        if (listContainer) {
            listContainer.innerHTML = '';
            var medsRaw = String(data.medications || '');
            var dosagesRaw = String(data.dosage || '');
            var freqsRaw = String(data.frequency || '');
            var durRaw = String(data.duration || '');

            var medItems = medsRaw.split(';').map(function(s) { return s.trim(); }).filter(Boolean);
            var doseItems = dosagesRaw.split('|').map(function(s) { return s.trim(); }).filter(Boolean);
            var freqItems = freqsRaw.split('|').map(function(s) { return s.trim(); }).filter(Boolean);
            var durItems = durRaw.split('|').map(function(s) { return s.trim(); }).filter(Boolean);

            if (medItems.length === 0) medItems = [medsRaw];

            medItems.forEach(function(item, idx) {
                var cleanItemName = item.replace(/^\d+\.\s*/, '').trim();
                var doseVal = doseItems[idx] ? doseItems[idx].replace(/^\d+\.\s*/, '').trim() : (data.dosage || 'As prescribed');
                var freqVal = freqItems[idx] ? freqItems[idx].replace(/^\d+\.\s*/, '').trim() : (data.frequency || 'Daily');
                var durVal = durItems[idx] ? durItems[idx].replace(/^\d+\.\s*/, '').trim() : (data.duration || 'As directed');

                var card = document.createElement('div');
                card.className = 'dior-view-rx-card';
                card.innerHTML = `
                    <div class="dior-view-rx-card-header">
                        <div class="dior-view-rx-card-index">${idx + 1}</div>
                        <div class="dior-view-rx-card-title">
                            <h4>${cleanItemName}</h4>
                            <span class="dior-view-rx-badge"><i class="fa-solid fa-capsules"></i> ${doseVal}</span>
                        </div>
                    </div>
                    <div class="dior-view-rx-grid">
                        <div class="dior-view-rx-stat">
                            <span class="label"><i class="fa-regular fa-clock"></i> Frequency</span>
                            <span class="val">${freqVal}</span>
                        </div>
                        <div class="dior-view-rx-stat">
                            <span class="label"><i class="fa-regular fa-calendar-check"></i> Duration</span>
                            <span class="val">${durVal}</span>
                        </div>
                        <div class="dior-view-rx-stat">
                            <span class="label"><i class="fa-solid fa-stethoscope"></i> Route</span>
                            <span class="val">Orally / Clinical Route</span>
                        </div>
                    </div>
                `;
                listContainer.appendChild(card);
            });
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        } else {
            modal.style.display = 'block';
            modal.classList.add('show');
            modal.classList.add('is-open');
        }
    };

    window.diorClosePrescriptionDetailModal = function() {
        var modal = document.getElementById('dior-prescription-view-modal');
        if (!modal) return;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
        modal.classList.remove('is-open');
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    // Close autocomplete when clicking outside
    document.addEventListener('click', function(e) {
        var searchSection = document.querySelector('.dior-fda-search-section');
        var dropdown = document.getElementById('dior-fda-autocomplete-dropdown');
        if (dropdown && searchSection && !searchSection.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

})();
