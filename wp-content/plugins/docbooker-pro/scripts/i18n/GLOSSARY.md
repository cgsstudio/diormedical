# DocBooker Pro translation glossary (same as the free plugin's)

Translations live in `scripts/i18n/<locale>/NNN.json` (English msgid → translation, or a list of
plural forms). They are the source: `.po`, `.mo` and the script `.json` files are built from them.

Pipeline, from the plugin folder:

1. `npm run i18n` (or wp-cli `i18n make-pot`) → `languages/doc-booker-pro.pot`
2. `python3 scripts/i18n/dump-strings.py 150` → `scripts/i18n/chunks/NNN.json`
3. translate into `scripts/i18n/<locale>/NNN.json`; new strings go in a new numbered file
4. `python3 scripts/i18n/validate.py <locale> <nplurals>`
5. `python3 scripts/i18n/build-po.py <locale> '<Plural-Forms>'` → `.po` + `.mo`
6. `NODE_PATH=<a node_modules with gettext-extractor> node scripts/i18n/js-strings.js`, then
   `python3 scripts/i18n/build-jed.py <locale>` → `languages/doc-booker-pro-<locale>-<handle>.json`

Plural forms: fr_FR `nplurals=2; plural=(n > 1);` · pl_PL
`nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);`
(forms are [one, few, many]) · ar `nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);`
(forms are [zero, one, two, few 3–10, many 11–99, other 100+]) · all others (incl. hi_IN, ta_IN) `nplurals=2; plural=(n != 1);`

## Tone

Plain, short, friendly; the same length as the English where possible. Address the reader as:
de_DE **du** (WordPress de_DE is informal; de_DE_formal is a separate locale) · fr_FR **vous** ·
es_ES / es_MX **tú** · it_IT **tu** · nl_NL **je** · pl_PL informal **ty** or impersonal forms ·
ar Modern Standard Arabic as in WordPress core's Arabic (imperatives for buttons, neutral wording;
Western digits stay as the placeholders give them) · hi_IN everyday Hindi with the English loanwords
Indian apps use (डॉक्टर, क्लिनिक, बुकिंग), polite **आप** · ta_IN everyday Tamil, polite **நீங்கள்**,
common loanwords where Tamil apps use them (கிளினிக், டாஷ்போர்டு).
Never translate: DocBooker, DocBooker Pro, Pro, Stripe, Razorpay, WordPress, Google Calendar,
Apple, Outlook, Leaflet, OpenStreetMap, Gutenberg, Elementor, shortcode tags like
`[wpddb_doctor_booking_form]`, placeholders (`%s`, `%1$s`, `%d`, `{{patient}}`), HTML tags.

## Terms

| English | de_DE | fr_FR | es_ES | es_MX | it_IT | nl_NL | pl_PL | ar | hi_IN | ta_IN |
|---|---|---|---|---|---|---|---|---|---|---|
| doctor | Arzt | médecin | médico | médico | medico | arts | lekarz | طبيب | डॉक्टर | மருத்துவர் |
| clinic | Klinik | clinique | clínica | clínica | clinica | kliniek | klinika | عيادة | क्लिनिक | கிளினிக் |
| department | Fachbereich | spécialité | especialidad | especialidad | reparto | specialisme | specjalizacja | تخصص | विभाग | துறை |
| appointment | Termin | rendez-vous | cita | cita | appuntamento | afspraak | wizyta | موعد | अपॉइंटमेंट | சந்திப்பு |
| booking | Buchung | réservation | reserva | reservación | prenotazione | boeking | rezerwacja | حجز | बुकिंग | முன்பதிவு |
| book (verb) | buchen | réserver | reservar | reservar | prenotare | boeken | zarezerwować | احجز | बुक करें | முன்பதிவு செய்யவும் |
| patient | Patient | patient | paciente | paciente | paziente | patiënt | pacjent | مريض | मरीज़ | நோயாளி |
| schedule | Sprechzeiten | horaires | horario | horario | orari | spreekuren | grafik | مواعيد العمل | समय-सारणी | நேர அட்டவணை |
| time slot | Uhrzeit | créneau | hora | horario | orario | tijd | godzina | وقت | समय | நேரம் |
| fee | Gebühr | tarif | tarifa | tarifa | tariffa | tarief | opłata | الرسوم | फ़ीस | கட்டணம் |
| pay at the clinic | in der Klinik bezahlen | payer à la clinique | pagar en la clínica | pagar en la clínica | pagare in clinica | betalen in de kliniek | płatność w klinice | الدفع في العيادة | क्लिनिक पर भुगतान | கிளினிக்கில் செலுத்துதல் |
| front desk / receptionist | Empfang | accueil | recepción | recepción | accettazione | balie | recepcja | الاستقبال | रिसेप्शन | வரவேற்பு |
| clinic manager | Klinikleitung | responsable de clinique | gerente de clínica | gerente de clínica | responsabile della clinica | kliniekbeheerder | kierownik kliniki | مدير العيادة | क्लिनिक मैनेजर | கிளினிக் மேலாளர் |
| reminder | Erinnerung | rappel | recordatorio | recordatorio | promemoria | herinnering | przypomnienie | تذكير | रिमाइंडर | நினைவூட்டல் |
| cancel (a booking) | stornieren | annuler | cancelar | cancelar | annullare | annuleren | odwołać | إلغاء | रद्द करें | ரத்துசெய் |
| holiday (doctor away) | Abwesenheit | absence | ausencia | ausencia | assenza | afwezigheid | nieobecność | غياب | छुट्टी | விடுப்பு |
| shortcode | Shortcode | shortcode | shortcode | shortcode | shortcode | shortcode | krótki kod | رمز مختصر | शॉर्टकोड | ஷார்ட்கோட் |
| Settings | Einstellungen | Réglages | Ajustes | Ajustes | Impostazioni | Instellingen | Ustawienia | الإعدادات | सेटिंग्स | அமைப்புகள் |
| Dashboard | Dashboard | Tableau de bord | Escritorio | Escritorio | Bacheca | Dashboard | Kokpit | لوحة التحكم | डैशबोर्ड | டாஷ்போர்டு |
| None (style setting) | Keine | Aucune | Ninguno | Ninguno | Nessuno | Geen | Brak | بلا | कोई नहीं | எதுவுமில்லை |
| Upcoming / Done / Cancelled | Anstehend / Erledigt / Storniert | À venir / Terminé / Annulé | Próxima / Realizada / Cancelada | Próxima / Realizada / Cancelada | In arrivo / Svolto / Annullato | Gepland / Afgerond / Geannuleerd | Nadchodzące / Zakończone / Odwołane | قادم / مكتمل / ملغى | आने वाली / पूरी हुई / रद्द | வரவிருக்கும் / முடிந்தது / ரத்துசெய்யப்பட்டது |
