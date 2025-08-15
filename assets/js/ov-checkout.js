// /* ovb-checkout.js */
// (function () {
//   const log = (...a) => (window.OVB_CFG && OVB_CFG.debug ? console.info("[OVB]", ...a) : undefined);
//   log("checkout script loaded");

//   const $ = (s, r = document) => r.querySelector(s);
//   const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

//   const byKeys = (keys) => {
//     for (const k of [].concat(keys)) {
//       const el = document.querySelector(`[name="${k}"]`) || document.getElementById(k);
//       if (el) return el;
//     }
//     return null;
//   };

//   const closestRow = (el) => {
//     if (!el) return null;
//     return (
//       el.closest(
//         ".form-row, .wc-block-components-text-input, .wc-block-components-combobox, .woocommerce-input-wrapper, .ov-form-group, .ov-field, .ov-row"
//       ) || el.parentElement
//     );
//   };

//   const ensureErrorNode = (row) => {
//     if (!row) return null;
//     let n = row.querySelector(".ovb-error-text");
//     if (!n) {
//       n = document.createElement("div");
//       n.className = "ovb-error-text";
//       row.appendChild(n);
//     }
//     return n;
//   };

//   const markInvalid = (el, msg) => {
//     const row = closestRow(el);
//     if (row) row.classList.add("ovb-invalid");
//     const n = ensureErrorNode(row);
//     if (n && msg) n.textContent = msg;
//   };

//   const clearInvalid = (el) => {
//     const row = closestRow(el);
//     if (row) {
//       row.classList.remove("ovb-invalid");
//       const n = row.querySelector(".ovb-error-text");
//       if (n) n.textContent = "";
//     }
//   };

//   const setRequired = (fields, on) => {
//     fields.forEach((f) => {
//       if (!f) return;
//       if (on) {
//         f.setAttribute("required", "required");
//         f.removeAttribute("disabled");
//       } else {
//         f.removeAttribute("required");
//         f.setAttribute("disabled", "disabled");
//         clearInvalid(f);
//       }
//     });
//   };

//   const setVisible = (wrap, on) => {
//     if (!wrap) return;
//     wrap.classList.toggle("is-hidden", !on);
//     if (!on) {
//       $$("input, select, textarea", wrap).forEach((el) => {
//         if (el.type === "checkbox" || el.type === "radio") el.checked = false;
//         else el.value = "";
//         clearInvalid(el);
//       });
//     }
//   };

//   // --- konfiguracija polja
//   const company = {
//     checkbox: ["ovb_is_company", "ovb_business_checkout", "billing_is_company"],
//     fieldsRequired: [
//       ["ovb_company_name", "billing_company"],
//       ["ovb_company_country", "billing_country"],
//       ["ovb_company_city", "billing_city"],
//       ["ovb_company_address", "ovb_company_address_1", "billing_address_1"],
//       ["ovb_company_postcode", "ovb_company_zip", "billing_postcode"],
//     ],
//     optional: [
//       ["ovb_company_pib", "ovb_company_vat"],
//       ["ovb_company_phone", "billing_phone_company"],
//     ],
//     wrappers: ["#ovb-company-fields", ".ovb-company-fields", '[data-ovb-group="company"]'],
//   };

//   const other = {
//     checkbox: ["ovb_is_other", "ovb_guest_different", "ovb_paid_by_other", "ovb-different-payer-checkbox"],
//     fields: [
//       ["ovb_other_first_name", "other_first_name"],
//       ["ovb_other_last_name", "other_last_name"],
//       ["ovb_other_email", "other_email"],
//       ["ovb_other_phone", "other_phone"],
//       ["ovb_other_dob", "other_dob"], // opciono
//     ],
//     wrappers: ["#ovb-other-fields", ".ovb-other-fields", '[data-ovb-group="other"]'],
//   };

//   const contact = {
//     first: ["ovb_contact_first_name", "billing_first_name"],
//     last: ["ovb_contact_last_name", "billing_last_name"],
//     email: ["ovb_contact_email", "billing_email"],
//     phone: ["ovb_contact_phone", "billing_phone"],
//   };

//   // --- stanje
//   const getState = () => {
//     const chkCompany = byKeys(company.checkbox);
//     const chkOther = byKeys(other.checkbox);
//     const companyOn = !!(chkCompany && (chkCompany.checked || chkCompany.value === "1" || chkCompany.value === "on"));
//     const otherOn = !!(chkOther && (chkOther.checked || chkOther.value === "1" || chkOther.value === "on"));
//     const companyWrap = company.wrappers.map((s) => $(s)).find(Boolean) || null;
//     const otherWrap = other.wrappers.map((s) => $(s)).find(Boolean) || null;

//     return {
//       chkCompany,
//       chkOther,
//       companyOn,
//       otherOn,
//       companyWrap,
//       otherWrap,
//       companyRequired: company.fieldsRequired.map(byKeys).filter(Boolean),
//       companyOptional: company.optional.map(byKeys).filter(Boolean),
//       otherFields: other.fields.map(byKeys).filter(Boolean),
//       contactFirst: byKeys(contact.first),
//       contactLast: byKeys(contact.last),
//       contactEmail: byKeys(contact.email),
//       contactPhone: byKeys(contact.phone),
//     };
//   };

//   const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
//   const phoneRx = /^\+?[0-9\s\-()]{6,}$/;

//   // --- UI: show/hide + required
//   const applyToggles = () => {
//     const st = getState();

//     setVisible(st.companyWrap, st.companyOn);
//     setVisible(st.otherWrap, st.otherOn);

//     setRequired(st.companyRequired, st.companyOn);

//     // Other: ime + prezime required; email/phone nisu required (OR logika — radi server-side), samo enable
//     const otherFirst = byKeys(["ovb_other_first_name", "other_first_name"]);
//     const otherLast = byKeys(["ovb_other_last_name", "other_last_name"]);
//     setRequired([otherFirst, otherLast], st.otherOn);
//     const otherEmail = byKeys(["ovb_other_email", "other_email"]);
//     const otherPhone = byKeys(["ovb_other_phone", "other_phone"]);
//     if (st.otherOn) {
//       if (otherEmail) otherEmail.removeAttribute("required"), otherEmail.removeAttribute("disabled");
//       if (otherPhone) otherPhone.removeAttribute("required"), otherPhone.removeAttribute("disabled");
//     } else {
//       if (otherEmail) otherEmail.removeAttribute("required"), otherEmail.setAttribute("disabled", "disabled");
//       if (otherPhone) otherPhone.removeAttribute("required"), otherPhone.setAttribute("disabled", "disabled");
//     }
//   };

//   // --- VALIDACIJA (client-side mirror server pravila)
//   const validateClient = () => {
//     const st = getState();
//     let ok = true;
//     const errors = [];

//     // početno čišćenje
//     [st.contactFirst, st.contactLast, st.contactEmail, st.contactPhone, ...st.companyRequired, ...st.otherFields].forEach(clearInvalid);

//     // Kontakt: uvek obavezno
//     if (st.contactFirst && !st.contactFirst.value.trim()) {
//       ok = false;
//       errors.push("Unesi ime kontakt osobe");
//       markInvalid(st.contactFirst);
//     }
//     if (st.contactLast && !st.contactLast.value.trim()) {
//       ok = false;
//       errors.push("Unesi prezime kontakt osobe");
//       markInvalid(st.contactLast);
//     }
//     if (st.contactEmail && !emailRx.test(st.contactEmail.value.trim())) {
//       ok = false;
//       errors.push("Unesi ispravan email kontakt osobe");
//       markInvalid(st.contactEmail);
//     }
//     if (st.contactPhone && !st.contactPhone.value.trim()) {
//       ok = false;
//       errors.push("Unesi telefon kontakt osobe");
//       markInvalid(st.contactPhone);
//     }

//     // Firma: kad je čekirano -> svih 5 polja
//     if (st.companyOn) {
//       const labels = ["Naziv firme", "Država", "Grad", "Adresa firme", "Poštanski broj"];
//       st.companyRequired.forEach((f, i) => {
//         if (!f.value.trim()) {
//           ok = false;
//           errors.push("Popunite: " + labels[i]);
//           markInvalid(f, "Obavezno polje");
//         }
//       });
//       // opciona validacija
//       const vat = byKeys(["ovb_company_pib", "ovb_company_vat"]);
//       if (vat && vat.value.trim() !== "" && !/^[0-9]{6,12}$/.test(vat.value.trim())) {
//         ok = false;
//         errors.push("PIB/VAT mora biti 6–12 cifara");
//         markInvalid(vat);
//       }
//       const cphone = byKeys(["ovb_company_phone", "billing_phone_company"]);
//       if (cphone && cphone.value.trim() !== "" && !phoneRx.test(cphone.value.trim())) {
//         ok = false;
//         errors.push("Telefon firme nije ispravan");
//         markInvalid(cphone);
//       }
//     }

//     // Plaća druga osoba: ime+prezime obavezni, email ILI telefon
//     if (st.otherOn) {
//       const of = byKeys(["ovb_other_first_name", "other_first_name"]);
//       const ol = byKeys(["ovb_other_last_name", "other_last_name"]);
//       const oe = byKeys(["ovb_other_email", "other_email"]);
//       const op = byKeys(["ovb_other_phone", "other_phone"]);
//       if (of && !of.value.trim()) {
//         ok = false;
//         errors.push("Unesi ime druge osobe");
//         markInvalid(of);
//       }
//       if (ol && !ol.value.trim()) {
//         ok = false;
//         errors.push("Unesi prezime druge osobe");
//         markInvalid(ol);
//       }

//       const hasEmail = oe && oe.value.trim() !== "";
//       const hasPhone = op && op.value.trim() !== "";

//       if (!hasEmail && !hasPhone) {
//         ok = false;
//         errors.push("Unesi email ili telefon druge osobe");
//         if (oe) markInvalid(oe);
//         if (op) markInvalid(op);
//       } else {
//         if (hasEmail && !emailRx.test(oe.value.trim())) {
//           ok = false;
//           errors.push("Email druge osobe nije ispravan");
//           markInvalid(oe);
//         }
//         if (hasPhone && !phoneRx.test(op.value.trim())) {
//           ok = false;
//           errors.push("Telefon druge osobe nije ispravan");
//           markInvalid(op);
//         }
//       }
//     }

//     return { ok, errors };
//   };

//   const renderTopNotice = (msgs) => {
//     const host = $(
//       ".wc-block-checkout__express-payment, .wc-block-components-checkout-step--contact-information, form.checkout, .wc-block-checkout"
//     );
//     if (!host) return;
//     let box = $("#ovb-client-errors");
//     if (!box) {
//       box = document.createElement("div");
//       box.id = "ovb-client-errors";
//       box.className = "ovb-notice";
//       host.parentElement.insertBefore(box, host);
//     }
//     box.innerHTML = msgs.map((m) => `<div>• ${m}</div>`).join("");
//     box.scrollIntoView({ behavior: "smooth", block: "center" });
//   };

//   const onSubmitIntercept = (e) => {
//     const { ok, errors } = validateClient();
//     if (!ok) {
//       e.preventDefault();
//       e.stopPropagation();
//       renderTopNotice(Array.from(new Set(errors)));
//       const firstInvalid = $(".ovb-invalid .input-text, .ovb-invalid input, .ovb-invalid select, .ovb-invalid textarea");
//       if (firstInvalid) firstInvalid.focus({ preventScroll: false });
//       log("blocked submit by client validation");
//       return false;
//     }
//     // pusti formu
//     return true;
//   };

//   const bind = () => {
//     // toggle listeners
//     const st = getState();
//     [st.chkCompany, st.chkOther].forEach((c) => {
//       if (c && !c.__ovbBound) {
//         c.addEventListener(
//           "change",
//           () => {
//             applyToggles();
//             // validateClient();
//           },
//           { passive: true }
//         );
//         c.__ovbBound = true;
//       }
//     });

//     // blur live validation
//     const watch = [...[st.contactFirst, st.contactLast, st.contactEmail, st.contactPhone], ...st.companyRequired, ...st.otherFields].filter(
//       Boolean
//     );

//     watch.forEach((el) => {
//       if (!el.__ovbBlur) {
//         el.addEventListener(
//           "blur",
//           () => {
//             clearInvalid(el);
//             // validateClient();
//           },
//           { passive: true }
//         );
//         el.__ovbBlur = true;
//       }
//     });

//     // submit intercept (Classic i Blocks)
//     const forms = $$('form[name="checkout"], form.woocommerce-checkout, form#checkout, .wc-block-checkout form');
//     forms.forEach((f) => {
//       if (!f.__ovbSubmit) {
//         f.addEventListener("submit", onSubmitIntercept, true);
//         f.__ovbSubmit = true;
//       }
//     });

//     // Place order button safety net
//     const btn = $('button[name="woocommerce_checkout_place_order"], .wc-block-components-checkout-place-order-button');
//     if (btn && !btn.__ovbClick) {
//       btn.addEventListener("click", (e) => {
//         const r = onSubmitIntercept(e);
//         if (!r) e.preventDefault();
//       });
//       btn.__ovbClick = true;
//     }
//   };

//   const boot = () => {
//     applyToggles();
//     bind();
//     log("boot applied");
//   };

//   // init
//   if (document.readyState === "loading") {
//     document.addEventListener("DOMContentLoaded", boot);
//   } else {
//     boot();
//   }

//   // Re-render (Woo Blocks/React)
//   let raf = null;
//   const mo = new MutationObserver(() => {
//     if (raf) cancelAnimationFrame(raf);
//     raf = requestAnimationFrame(boot);
//   });
//   mo.observe(document.body, { childList: true, subtree: true });

//   // Debug hook
//   window.OVBCheckoutInit = boot;
// })();
