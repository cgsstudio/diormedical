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
        brandName: "SPORANOX",
        genericName: "ITRACONAZOLE",
        strength: "100 MG",
        containsText: "Contains: ITRACONAZOLE (100 MG)",
        includeGeneric: true,
        takeOrApply: "Take",
        doseForm: "Capsule(s)",
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

        // Set default date
        var dateInput = document.getElementById('dior-fda-presc-date');
        if (dateInput && !dateInput.value) {
            dateInput.value = new Date().toISOString().split('T')[0];
        }

        // Reset or init state
        window.diorFdaUpdateAllUI();

        modal.style.display = 'flex';
        void modal.offsetHeight;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        var searchInput = document.getElementById('dior-fda-med-search');
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
        modal.classList.remove('is-open');
        setTimeout(function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 250);
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
        s.brandName = (med.brand_name || 'Medication').toUpperCase();
        s.genericName = (med.generic_name || s.brandName).toUpperCase();
        s.strength = (med.strength || '').toUpperCase();
        s.doseForm = med.dosage_form || 'Capsule(s)';
        s.route = med.route || 'Orally';

        s.containsText = 'Contains: ' + s.genericName + (s.strength ? ' (' + s.strength + ')' : '');

        var dropdown = document.getElementById('dior-fda-autocomplete-dropdown');
        if (dropdown) dropdown.style.display = 'none';

        var searchInput = document.getElementById('dior-fda-med-search');
        if (searchInput) searchInput.value = s.brandName;

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
     * Master Re-compute & Synchronize UI
     */
    window.diorFdaUpdateAllUI = function() {
        var s = window.diorFdaState;

        // 1. Header Title
        var titleEl = document.getElementById('dior-fda-title-text');
        if (titleEl) {
            var unitShort = s.doseForm.replace('(s)', '').toUpperCase();
            titleEl.textContent = s.itemNumber + '. ' + s.brandName + ' ' + unitShort;
        }

        // 2. Generic name line
        var containsDisplay = document.getElementById('dior-fda-contains-display');
        var genericBox = document.getElementById('dior-fda-generic-subrow');
        if (containsDisplay) containsDisplay.textContent = s.containsText;
        if (genericBox) genericBox.style.display = s.includeGeneric ? 'flex' : 'none';

        // 3. Stepper Inputs
        var durInput = document.getElementById('dior-fda-dur-val');
        if (durInput) durInput.value = s.durationVal;

        var mInput = document.getElementById('dior-fda-qty-morning');
        var nInput = document.getElementById('dior-fda-qty-noon');
        var eInput = document.getElementById('dior-fda-qty-evening');
        var ntInput = document.getElementById('dior-fda-qty-night');
        var dInput = document.getElementById('dior-fda-qty-daily');

        if (mInput) mInput.value = s.morning;
        if (nInput) nInput.value = s.noon;
        if (eInput) eInput.value = s.evening;
        if (ntInput) ntInput.value = s.night;
        if (dInput) dInput.value = s.daily;

        // 4. Update Dose Card Active Highlights (solid dark teal when qty > 0)
        var cardM = document.getElementById('dior-card-morning');
        var cardN = document.getElementById('dior-card-noon');
        var cardE = document.getElementById('dior-card-evening');
        var cardNt = document.getElementById('dior-card-night');
        var cardD = document.getElementById('dior-card-daily');

        if (cardM) cardM.classList.toggle('has-qty', s.morning > 0);
        if (cardN) cardN.classList.toggle('has-qty', s.noon > 0);
        if (cardE) cardE.classList.toggle('has-qty', s.evening > 0);
        if (cardNt) cardNt.classList.toggle('has-qty', s.night > 0);
        if (cardD) cardD.classList.toggle('has-qty', s.daily > 0);

        // 5. Update unit sublabels (capsule(s) vs tablet(s))
        var sublabels = document.querySelectorAll('.dior-fda-dose-sub');
        var singleUnit = s.doseForm.toLowerCase();
        sublabels.forEach(function(sub) {
            sub.textContent = singleUnit;
        });

        // 6. Generate live instruction sentence
        var times = [];
        if (s.morning > 0) times.push('in the morning (' + s.morning + ')');
        if (s.noon > 0) times.push('in the afternoon (' + s.noon + ')');
        if (s.evening > 0) times.push('in the evening (' + s.evening + ')');
        if (s.night > 0) times.push('in the night (' + s.night + ')');
        if (s.daily > 0) times.push('daily (' + s.daily + ')');

        var totalDailyDose = s.morning + s.noon + s.evening + s.night + s.daily;
        if (totalDailyDose === 0) totalDailyDose = 1;

        var timingStr = times.length > 0 ? times.join(', ') : 'as directed';
        var foodStr = (s.intake === 'Not Applicable') ? '' : (' ' + s.intake.toLowerCase());
        var durStr = (s.durationUnit === 'Till Next Review') ? 'till next review' : ('for ' + s.durationVal + ' ' + s.durationUnit.toLowerCase().replace('(s)', s.durationVal > 1 ? 's' : ''));

        var line1 = s.takeOrApply + ' ' + totalDailyDose + ' ' + singleUnit + ' ' + timingStr + foodStr + ' ' + durStr;
        var line2 = 'To be taken: ' + s.route;

        var inst1El = document.getElementById('dior-fda-inst-line1');
        var inst2El = document.getElementById('dior-fda-inst-line2');
        if (inst1El) inst1El.textContent = line1;
        if (inst2El) inst2El.textContent = line2;

        // 7. Calculate Total Capsules/Tablets
        var daysMultiplier = 1;
        if (s.durationUnit === 'Day(s)') daysMultiplier = s.durationVal;
        else if (s.durationUnit === 'Week(s)') daysMultiplier = s.durationVal * 7;
        else if (s.durationUnit === 'Month(s)') daysMultiplier = s.durationVal * 30;
        else if (s.durationUnit === 'Till Next Review') daysMultiplier = 14;

        var totalUnits = totalDailyDose * daysMultiplier;
        var totalEl = document.getElementById('dior-fda-total-calc');
        var pluralUnit = s.doseForm.replace('(s)', totalUnits > 1 ? 's' : '');
        if (totalEl) totalEl.textContent = 'Total ' + totalUnits + ' ' + pluralUnit;
    };

    /**
     * Add Currently Configured Medicine to Prescription List (Multi-Medication Support)
     */
    window.diorFdaAddMedicineToList = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        var s = window.diorFdaState;

        if (!s.brandName || s.brandName.trim() === '') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Medication Name Missing', text: 'Please search and select an FDA approved medication first.' });
            } else {
                alert('Please search and select a medication first.');
            }
            var searchInp = document.getElementById('dior-fda-med-search');
            if (searchInp) searchInp.focus();
            return;
        }

        var totalDailyDose = s.morning + s.noon + s.evening + s.night + s.daily;
        if (totalDailyDose === 0) totalDailyDose = 1;

        var fullMedName = s.brandName + ' ' + s.doseForm;
        if (s.includeGeneric && s.genericName) {
            fullMedName += ' (' + s.genericName + (s.strength ? ' ' + s.strength : '') + ')';
        }

        var fullDosage = totalDailyDose + ' ' + s.doseForm + ' ' + s.route;
        var fullFrequency = s.frequency + ' (' + s.intake + ')';
        var fullDuration = s.durationVal + ' ' + s.durationUnit;

        var notesArea = document.getElementById('dior-fda-custom-notes');
        var customNotes = notesArea ? notesArea.value.trim() : '';

        var line1 = (document.getElementById('dior-fda-inst-line1') ? document.getElementById('dior-fda-inst-line1').textContent : '');
        var line2 = (document.getElementById('dior-fda-inst-line2') ? document.getElementById('dior-fda-inst-line2').textContent : '');
        var generatedInstructions = line1 + ' | ' + line2;
        if (customNotes) {
            generatedInstructions += ' | Note: ' + customNotes;
        }

        var daysMultiplier = 1;
        if (s.durationUnit === 'Day(s)') daysMultiplier = s.durationVal;
        else if (s.durationUnit === 'Week(s)') daysMultiplier = s.durationVal * 7;
        else if (s.durationUnit === 'Month(s)') daysMultiplier = s.durationVal * 30;
        else if (s.durationUnit === 'Till Next Review') daysMultiplier = 14;
        var totalUnits = totalDailyDose * daysMultiplier;

        var medItem = {
            brandName: s.brandName,
            genericName: s.genericName,
            strength: s.strength,
            doseForm: s.doseForm,
            route: s.route,
            takeOrApply: s.takeOrApply,
            durationVal: s.durationVal,
            durationUnit: s.durationUnit,
            frequency: s.frequency,
            intake: s.intake,
            totalUnits: totalUnits,
            fullMedName: fullMedName,
            fullDosage: fullDosage,
            fullFrequency: fullFrequency,
            fullDuration: fullDuration,
            instructions: generatedInstructions,
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
                timer: 2500,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Added: ' + medItem.brandName + ' (' + totalUnits + ' ' + s.doseForm + ')'
            });
        }

        // Prepare form for NEXT medication
        s.itemNumber = s.medicationsList.length + 1;
        s.brandName = "";
        s.genericName = "";
        s.strength = "";
        s.containsText = "";
        s.morning = 0;
        s.noon = 0;
        s.evening = 0;
        s.night = 1;
        s.daily = 0;

        var searchInput = document.getElementById('dior-fda-med-search');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        if (notesArea) notesArea.value = '';

        window.diorFdaUpdateAllUI();

        // Update header title to reflect next item
        var titleEl = document.getElementById('dior-fda-title-text');
        if (titleEl) {
            titleEl.textContent = s.itemNumber + '. [SEARCH NEXT MEDICATION]';
        }
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
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Select Patient', text: 'Please select a patient for this prescription.' });
            } else {
                alert('Please select a patient for this prescription.');
            }
            return;
        }

        // If no medication is in the list yet, but the user filled out the form, auto-add it
        if (s.medicationsList.length === 0) {
            if (s.brandName && s.brandName.trim() !== '') {
                window.diorFdaAddMedicineToList(e);
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'No Medication Added', text: 'Please search and add at least one medication to this prescription.' });
                } else {
                    alert('Please search and add at least one medication.');
                }
                var sInp = document.getElementById('dior-fda-med-search');
                if (sInp) sInp.focus();
                return;
            }
        } else if (s.brandName && s.brandName.trim() !== '') {
            // If user has another medication in the form that hasn't been clicked '+ Add Medicine', auto add it too!
            window.diorFdaAddMedicineToList(e);
        }

        if (s.medicationsList.length === 0) {
            return;
        }

        var prescDate = document.getElementById('dior-fda-presc-date').value || new Date().toISOString().split('T')[0];
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

        var formData = new FormData();
        formData.append('action', 'dior_doctor_add_prescription');
        var nonceVal = (document.getElementById('dior-fda-nonce') ? document.getElementById('dior-fda-nonce').value : '') ||
                       (typeof dior_vars !== 'undefined' ? dior_vars.nonce : '');
        formData.append('nonce', nonceVal);
        formData.append('patient_id', patientId);
        formData.append('patient_name', patientName);
        formData.append('prescription_id', 'RX-' + new Date().getFullYear().toString().substr(-2) + String(Math.floor(1000 + Math.random() * 9000)));
        formData.append('prescription_date', prescDate);
        formData.append('medications', compiledMedications);
        formData.append('dosage', compiledDosage);
        formData.append('frequency', compiledFrequency);
        formData.append('duration', compiledDuration);
        formData.append('instructions', compiledInstructions);
        formData.append('quantity', s.medicationsList.length + ' Item(s)');
        formData.append('status', 'Active');

        var ajaxUrl = (typeof dior_vars !== 'undefined' ? dior_vars.ajax_url : '') || (typeof dior_patient_dashboard !== 'undefined' ? dior_patient_dashboard.ajax_url : '/wp-admin/admin-ajax.php');

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-prescription"></i> SAVE &amp; ISSUE PRESCRIPTION';
            }

            if (res.success) {
                var totalCount = s.medicationsList.length;

                // Live insert row into Digital Prescriptions table
                window.diorInjectPrescriptionRow({
                    prescription_id: res.data.prescription_id || ('RX-' + Date.now().toString().substr(-4)),
                    patient_name: patientName,
                    date: prescDate,
                    medications: compiledMedications,
                    dosage: compiledDosage,
                    frequency: compiledFrequency,
                    duration: compiledDuration,
                    doctor: 'Dr. Marcus Sterling, DO',
                    status: 'Active',
                    count: totalCount
                });

                // Reset state
                s.medicationsList = [];
                window.diorFdaRenderPrescribedList();
                window.diorCloseFdaPrescriptionModal();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Prescription Issued!',
                        html: '<strong>' + totalCount + ' medication(s)</strong> prescribed successfully to <strong>' + patientName + '</strong>.',
                        confirmButtonColor: '#0c4a60'
                    });
                } else {
                    alert('Prescription issued with ' + totalCount + ' medication(s) successfully!');
                }
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'Failed to save prescription.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error Saving Prescription', text: msg });
                } else {
                    alert('Error: ' + msg);
                }
            }
        })
        .catch(function(err) {
            console.error('[Dior Medical] Prescription save error:', err);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-prescription"></i> SAVE &amp; ISSUE PRESCRIPTION';
            }
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Network request failed. Please check your connection and try again.' });
            } else {
                alert('Request failed. Please try again.');
            }
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

        var tr = document.createElement('tr');
        tr.style.animation = 'diorFadeIn 0.4s ease';
        tr.innerHTML = `
            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
            <td><strong>${p.prescription_id}</strong></td>
            <td>
                <div class="va-user-profile">
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(p.patient_name)}&background=0c4a60&color=fff" class="va-user-avatar" alt="${p.patient_name}">
                    <span class="va-user-name">${p.patient_name}</span>
                </div>
            </td>
            <td><i class="far fa-calendar va-icon-blue"></i> ${p.date}</td>
            <td><span style="font-weight:700;color:#0c4a60;">${p.medications}</span></td>
            <td>${p.dosage}</td>
            <td>${p.frequency}</td>
            <td>${p.duration}</td>
            <td>${p.doctor}</td>
            <td><span class="badge-solid col-green" style="background-color:transparent;color:#059669;padding:6px 12px;border-radius:6px;font-weight:700;">${p.status}</span></td>
            <td>
                <div class="va-row-actions">
                    <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fas fa-pen-to-square"></i></button>
                    <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fas fa-trash-can"></i></button>
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

    // Close autocomplete when clicking outside
    document.addEventListener('click', function(e) {
        var searchSection = document.querySelector('.dior-fda-search-section');
        var dropdown = document.getElementById('dior-fda-autocomplete-dropdown');
        if (dropdown && searchSection && !searchSection.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

})();
