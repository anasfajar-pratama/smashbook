/* ===========================================================
   SmashBook — aplikasi JS (modal, tab, kalkulator berbasis server)
   =========================================================== */

(function () {
  "use strict";

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const digits = (value) => Number(String(value ?? "").replace(/\D/g, "") || 0);
  const rupiah = (value) =>
    "Rp " + Number(value || 0).toLocaleString("id-ID", { maximumFractionDigits: 0 });

  window.rupiah = rupiah;

  window.openModal = function (id) {
    const el = document.getElementById(id);
    if (el) el.classList.add("open");
  };

  window.closeModal = function (id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove("open");
  };

  document.addEventListener("click", (e) => {
    if (e.target.classList && e.target.classList.contains("modal-mask")) {
      e.target.classList.remove("open");
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      $$(".modal-mask.open").forEach((m) => m.classList.remove("open"));
    }
  });

  document.addEventListener("submit", (e) => {
    const form = e.target;
    if (form.dataset && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
      e.preventDefault();
    }
  });

  /* ---------- Copy to clipboard (pesan WhatsApp / teks) ---------- */
  window.copyText = function (text, label) {
    const done = () => {
      if (window.toast) window.toast((label || "Teks") + " disalin.");
      else alert((label || "Teks") + " disalin.");
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(() => fallback());
    } else {
      fallback();
    }

    function fallback() {
      const ta = document.createElement("textarea");
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      document.body.removeChild(ta);
      done();
    }
  };

  /* ---------- Toast ---------- */
  window.toast = function (message, tone) {
    let el = document.getElementById("sbToast");
    if (!el) {
      el = document.createElement("div");
      el.id = "sbToast";
      el.style.cssText =
        "position:fixed;right:18px;bottom:18px;z-index:120;background:#0f172a;color:#fff;padding:11px 15px;border-radius:10px;font-size:13px;box-shadow:0 12px 30px -12px rgba(15,23,42,.5);max-width:360px";
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.style.background = tone === "error" ? "#991b1b" : tone === "warn" ? "#92400e" : "#0f172a";
    el.style.display = "block";
    clearTimeout(el._timer);
    el._timer = setTimeout(() => (el.style.display = "none"), 3200);
  };

  /* ---------- Booking form + live quote ---------- */
  function initBookingForm() {
    const form = document.getElementById("bookingForm");
    if (!form || !window.SMASHBOOK || !window.SMASHBOOK.quoteUrl) return;

    const court = $("#b_court");
    const date = $("#b_date");
    const start = $("#b_start");
    const duration = $("#b_duration");
    const member = $("#b_member");
    const breakdown = $("#b_rincian");
    const totalEl = $("#b_total");
    const payAmount = $("#b_payment_amount");
    const payMethod = $("#b_payment_method");
    const dpBtn = $("#b_dp_btn");
    const fullBtn = $("#b_full_btn");
    const venueBtn = $("#b_venue_btn");
    const payAtVenue = $("#b_pay_at_venue");
    const quoteState = { total: 0, dp: 0 };
    let seq = 0;

    const setPay = (value) => {
      if (payAmount) payAmount.value = Math.round(value);
    };

    const setVenue = (on) => {
      if (payAtVenue) payAtVenue.value = on ? "1" : "0";
      if (venueBtn) venueBtn.classList.toggle("btn-primary", on);
    };

    function refresh() {
      if (!court?.value || !date?.value || !start?.value || !duration?.value) return;

      const params = new URLSearchParams({
        court_id: court.value,
        booking_date: date.value,
        start_time: start.value,
        duration_minutes: duration.value,
        member_id: member?.value || "",
      });

      const current = ++seq;

      fetch(window.SMASHBOOK.quoteUrl + "?" + params.toString(), {
        headers: { Accept: "application/json" },
      })
        .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
          if (current !== seq) return;

          if (!ok) {
            const message =
              (data.errors && Object.values(data.errors).flat()[0]) ||
              data.message ||
              "Tarif tidak tersedia untuk pilihan ini.";
            if (breakdown) {
              breakdown.innerHTML =
                '<div class="alert err"><span class="ic">⚠️</span><div>' + message + "</div></div>";
            }
            quoteState.total = 0;
            quoteState.dp = 0;
            if (totalEl) totalEl.value = "";
            return;
          }

          if (breakdown) {
            breakdown.innerHTML =
              data.slots
                .map(
                  (s) =>
                    '<div class="sum"><span class="lbl">' +
                    s.time +
                    "–" +
                    s.end +
                    " · " +
                    s.category +
                    '</span><span>' +
                    rupiah(s.price) +
                    ' <span class="muted-text">/ 30 mnt</span></span></div>'
                )
                .join("") +
              '<div class="sum"><span class="lbl">Subtotal</span><span>' +
              rupiah(data.base_amount) +
              "</span></div>" +
              (data.discount_amount > 0
                ? '<div class="sum"><span class="lbl">Diskon member ' +
                  data.discount_pct +
                  '%</span><span class="text-red">− ' +
                  rupiah(data.discount_amount) +
                  "</span></div>"
                : "") +
              '<div class="sum big"><span>Total</span><span>' +
              rupiah(data.total_amount) +
              "</span></div>";
          }

          quoteState.total = Number(data.total_amount || 0);
          quoteState.dp = Math.round((quoteState.total * Number(data.dp_percent || 0)) / 100);

          if (totalEl) totalEl.value = rupiah(quoteState.total);

          const hint = $("#b_dp_hint");
          if (hint) hint.textContent = data.hold_minutes + " menit";

          if (payMethod && payMethod.value === "deposit" && member && !member.value) {
            window.toast("Pilih member untuk pembayaran deposit.", "warn");
          }
        })
        .catch(() => {
          if (breakdown) {
            breakdown.innerHTML =
              '<div class="alert err"><span class="ic">⚠️</span><div>Gagal menghitung tarif. Periksa koneksi.</div></div>';
          }
        });
    }

    [court, date, start, duration, member].forEach((el) => {
      if (el) el.addEventListener("change", refresh);
    });

    if (member) {
      member.addEventListener("change", () => {
        const opt = member.options[member.selectedIndex];
        const name = $("#b_name");
        const phone = $("#b_phone");
        if (opt && opt.value && name && !name.value) name.value = opt.dataset.name || "";
        if (opt && opt.value && phone && !phone.value) phone.value = opt.dataset.phone || "";
      });
    }

    if (payAmount) {
      payAmount.addEventListener("input", () => {
        if (digits(payAmount.value) > 0) setVenue(false);
      });
    }

    if (dpBtn) dpBtn.addEventListener("click", () => {
      setVenue(false);
      setPay(quoteState.dp);
    });
    if (fullBtn) fullBtn.addEventListener("click", () => {
      setVenue(false);
      setPay(quoteState.total);
    });
    if (venueBtn) venueBtn.addEventListener("click", () => {
      setVenue(true);
      setPay(0);
      window.toast("Booking akan dikonfirmasi sebagai bayar di tempat.", "warn");
    });

    window.refreshBookingQuote = refresh;
    refresh();
  }

  window.pickSlot = function (courtId, hour) {
    const court = document.getElementById("b_court");
    const start = document.getElementById("b_start");
    const date = document.getElementById("b_date");
    if (court) court.value = String(courtId);
    if (start) start.value = hour;
    if (date && !date.value) {
      const d = new Date();
      date.value = d.toISOString().slice(0, 10);
    }
    if (window.refreshBookingQuote) window.refreshBookingQuote();
    window.openModal("modalBooking");
  };

  /* ---------- POS cart ---------- */
  function initPos() {
    const root = document.getElementById("posApp");
    if (!root) return;

    const cart = [];
    const itemsEl = document.getElementById("cartItems");
    const inputsEl = document.getElementById("cartInputs");
    const subtotalEl = document.getElementById("cartSubtotal");
    const discountEl = document.getElementById("cartDiskon");
    const totalEl = document.getElementById("cartTotal");
    const countEl = document.getElementById("cartJumlah");
    const discountInput = document.getElementById("discount_amount");
    const memberSelect = document.getElementById("member_id");
    const cashInput = document.getElementById("cash_received");
    const qrisInput = document.getElementById("qris");
    const transferInput = document.getElementById("transfer");
    const debitInput = document.getElementById("debit");
    const depositInput = document.getElementById("deposit");
    const dueCashEl = document.getElementById("dueCash");
    const changeEl = document.getElementById("cartKembali");
    const warningEl = document.getElementById("paymentWarning");
    const pinRow = document.getElementById("discountPinRow");
    const form = document.getElementById("posForm");
    const search = document.getElementById("posSearch");

    const discountPct = Number(root.dataset.discountLimit || 20);

    function totals() {
      const subtotal = cart.reduce((sum, item) => sum + item.price * item.qty, 0);
      const manualDiscount = Math.min(digits(discountInput?.value), subtotal);
      const total = Math.max(subtotal - manualDiscount, 0);
      const nonCash =
        digits(qrisInput?.value) +
        digits(transferInput?.value) +
        digits(debitInput?.value) +
        digits(depositInput?.value);
      const dueCash = Math.max(total - nonCash, 0);

      return { subtotal, manualDiscount, total, nonCash, dueCash };
    }

    function render() {
      if (!itemsEl) return;

      if (!cart.length) {
        itemsEl.innerHTML =
          '<div class="empty cart-empty"><span class="ic">🛒</span>Keranjang masih kosong</div>';
      } else {
        itemsEl.innerHTML = cart
          .map(
            (item, index) => `
          <div class="cart-item">
            <div>
              <div class="nm">${escapeHtml(item.name)}</div>
              <div class="meta">${rupiah(item.price)} × ${item.qty}</div>
            </div>
            <div class="amt">${rupiah(item.price * item.qty)}</div>
            <div class="row" style="grid-column:1/-1;justify-content:space-between">
              <div class="qty">
                <button type="button" data-dec="${index}">−</button>
                <span>${item.qty}</span>
                <button type="button" data-inc="${index}">+</button>
              </div>
              <button class="btn btn-sm btn-ghost" type="button" data-del="${index}">Hapus</button>
            </div>
          </div>`
          )
          .join("");
      }

      if (inputsEl) {
        inputsEl.innerHTML = cart
          .map(
            (item, index) =>
              `<input type="hidden" name="items[${index}][product_id]" value="${item.id}">
               <input type="hidden" name="items[${index}][qty]" value="${item.qty}">`
          )
          .join("");
      }

      const t = totals();
      if (subtotalEl) subtotalEl.textContent = rupiah(t.subtotal);
      if (discountEl) discountEl.textContent = "− " + rupiah(t.manualDiscount);
      if (totalEl) totalEl.textContent = rupiah(t.total);
      if (countEl) countEl.textContent = cart.reduce((n, i) => n + i.qty, 0) + " item";
      if (dueCashEl) dueCashEl.textContent = rupiah(t.dueCash);

      const cashReceived = digits(cashInput?.value);
      const change = Math.max(cashReceived - t.dueCash, 0);
      if (changeEl) changeEl.textContent = rupiah(change);

      if (warningEl) {
        if (t.nonCash > t.total) {
          warningEl.style.display = "";
          warningEl.innerHTML =
            '<div class="alert err"><span class="ic">⚠️</span><div>Pembayaran non-tunai melebihi total transaksi.</div></div>';
        } else {
          warningEl.style.display = "none";
        }
      }

      if (pinRow) {
        const overLimit = t.manualDiscount > (t.subtotal * discountPct) / 100 && t.manualDiscount > 0;
        pinRow.classList.toggle("show", overLimit);
      }
    }

    function addProduct(button) {
      if (button.classList.contains("out")) {
        window.toast("Stok habis — lakukan stok masuk atau Quick PO.", "warn");
        return;
      }

      const id = Number(button.dataset.id);
      const stock = Number(button.dataset.stock);
      const existing = cart.find((item) => item.id === id);

      if (existing) {
        if (stock >= 0 && existing.qty + 1 > stock) {
          window.toast("Qty melebihi stok tersedia.", "warn");
          return;
        }
        existing.qty++;
      } else {
        cart.push({
          id,
          name: button.dataset.name,
          price: Number(button.dataset.price),
          qty: 1,
          stock,
        });
      }

      render();
    }

    $$(".prod[data-id]", root).forEach((button) => {
      button.addEventListener("click", () => addProduct(button));
    });

    if (itemsEl) {
      itemsEl.addEventListener("click", (e) => {
        const inc = e.target.closest("[data-inc]");
        const dec = e.target.closest("[data-dec]");
        const del = e.target.closest("[data-del]");

        if (inc) {
          const index = Number(inc.dataset.inc);
          if (cart[index].stock >= 0 && cart[index].qty + 1 > cart[index].stock) {
            window.toast("Qty melebihi stok tersedia.", "warn");
            return;
          }
          cart[index].qty++;
          render();
        }
        if (dec) {
          const index = Number(dec.dataset.dec);
          cart[index].qty--;
          if (cart[index].qty <= 0) cart.splice(index, 1);
          render();
        }
        if (del) {
          cart.splice(Number(del.dataset.del), 1);
          render();
        }
      });
    }

    [discountInput, memberSelect, cashInput, qrisInput, transferInput, debitInput, depositInput].forEach(
      (el) => el && el.addEventListener("input", render)
    );

    $$("[data-cash]").forEach((button) => {
      button.addEventListener("click", () => {
        const t = totals();
        if (cashInput) {
          cashInput.value = button.dataset.cash === "pas" ? Math.round(t.dueCash) : Number(button.dataset.cash);
        }
        render();
      });
    });

    if (search) {
      search.addEventListener("input", () => {
        const q = search.value.toLowerCase();
        $$(".prod[data-id]", root).forEach((tile) => {
          const text = (tile.dataset.name + " " + (tile.dataset.sku || "")).toLowerCase();
          tile.style.display = text.includes(q) ? "" : "none";
        });
      });
    }

    document.addEventListener("keydown", (e) => {
      if (e.key === "F2" && search) {
        e.preventDefault();
        search.focus();
        search.select();
      }
      if (e.key === "F8" && cashInput) {
        e.preventDefault();
        cashInput.focus();
        cashInput.select();
      }
    });

    $$(".chip[data-category]", root).forEach((chip) => {
      chip.addEventListener("click", () => {
        $$(".chip[data-category]", root).forEach((c) => c.classList.remove("on"));
        chip.classList.add("on");
        const category = chip.dataset.category;
        $$(".prod[data-id]", root).forEach((tile) => {
          tile.style.display = category === "all" || tile.dataset.category === category ? "" : "none";
        });
      });
    });

    if (form) {
      form.addEventListener("submit", (e) => {
        if (!cart.length) {
          e.preventDefault();
          window.toast("Keranjang masih kosong.", "warn");
          return;
        }

        const t = totals();
        const cashReceived = digits(cashInput?.value);

        if (t.nonCash > t.total) {
          e.preventDefault();
          window.toast("Pembayaran non-tunai melebihi total.", "error");
          return;
        }

        if (cashReceived + 0.01 < t.dueCash) {
          e.preventDefault();
          window.toast("Uang tunai diterima kurang dari jumlah yang harus dibayar.", "error");
        }
      });
    }

    render();
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  /* ---------- Cash difference ---------- */
  function initCash() {
    const input = document.getElementById("fisikInput");
    const expectedEl = document.getElementById("expectedVal");
    if (!input || !expectedEl) return;

    const expected = Number(expectedEl.dataset.val || 0);
    const box = document.getElementById("selisihBox");
    const tolerance = Number(expectedEl.dataset.tolerance || 10000);

    function calc() {
      if (!input.value.trim()) {
        if (box) box.style.display = "none";
        const pin = document.getElementById("ownerPinWrap");
        if (pin) pin.style.display = "none";
        return;
      }

      const physical = digits(input.value);
      const diff = physical - expected;
      if (!box) return;
      box.style.display = "";
      const valueEl = box.querySelector(".v");
      const noteEl = box.querySelector(".ket");
      valueEl.textContent = (diff > 0 ? "+ " : diff < 0 ? "− " : "") + rupiah(Math.abs(diff));
      valueEl.style.color = diff === 0 ? "#047857" : Math.abs(diff) <= tolerance ? "#b45309" : "#b91c1c";
      noteEl.textContent =
        diff === 0
          ? "Kas sesuai, aman untuk ditutup."
          : Math.abs(diff) <= tolerance
          ? "Selisih dalam batas toleransi (≤ " + rupiah(tolerance) + "), tetap dicatat."
          : "Selisih melebihi toleransi — wajib catatan alasan & PIN Owner.";
      const pin = document.getElementById("ownerPinWrap");
      if (pin) pin.style.display = Math.abs(diff) > tolerance ? "" : "none";
    }

    input.addEventListener("input", calc);
    $$("[data-simulate]").forEach((button) => {
      button.addEventListener("click", () => {
        input.value = button.dataset.simulate;
        calc();
      });
    });
    calc();
  }

  /* ---------- Purchase order form ---------- */
  function initPoForm() {
    const form = document.getElementById("poForm");
    if (!form) return;

    const rowsEl = document.getElementById("poItems");
    const template = document.getElementById("poItemTemplate");
    const subtotalEl = document.getElementById("poSubtotal");
    const totalEl = document.getElementById("poTotal");
    const discountEl = $("#poDiscount");
    const taxEl = $("#poTax");
    const shippingEl = $("#poShipping");
    const threshold = Number(form.dataset.adminThreshold || 500000);
    const ownerThreshold = Number(form.dataset.ownerThreshold || 2000000);
    const badge = document.getElementById("poApproval");

    function updateRow(row) {
      const type = row.querySelector(".po-type")?.value;
      const productSelect = row.querySelector(".po-product");
      const productWrap = row.querySelector(".po-product-wrap");
      const expenseWrap = row.querySelector(".po-expense-wrap");

      if (productWrap) productWrap.style.display = type === "product" ? "" : "none";
      if (expenseWrap) expenseWrap.style.display = type === "expense" ? "" : "none";

      if (type === "product" && productSelect) {
        const opt = productSelect.options[productSelect.selectedIndex];
        if (opt && opt.value) {
          const description = row.querySelector(".po-description");
          const unit = row.querySelector(".po-unit");
          const price = row.querySelector(".po-price");
          if (description && !description.value) description.value = opt.dataset.name || "";
          if (unit) unit.value = opt.dataset.purchaseUnit || opt.dataset.unit || "";
          if (price && Number(price.value) === 0) {
            const conversion = Number(opt.dataset.conversion || 1);
            price.value = Math.round(Number(opt.dataset.cost || 0) * conversion);
          }
        }
      }

      const qty = Number(row.querySelector(".po-qty")?.value || 0);
      const price = Number(row.querySelector(".po-price")?.value || 0);
      const amount = qty * price;
      const amountEl = row.querySelector(".po-amount");
      if (amountEl) amountEl.textContent = rupiah(amount);

      return amount;
    }

    function recalc() {
      let subtotal = 0;
      $$(".po-row", rowsEl).forEach((row) => {
        subtotal += updateRow(row);
      });

      const discount = Number(discountEl?.value || 0);
      const tax = Number(taxEl?.value || 0);
      const shipping = Number(shippingEl?.value || 0);
      const total = Math.max(subtotal - discount + tax + shipping, 0);

      if (subtotalEl) subtotalEl.textContent = rupiah(subtotal);
      if (totalEl) totalEl.textContent = rupiah(total);

      if (badge) {
        if (total <= threshold) {
          badge.className = "badge green";
          badge.textContent = "Auto-approve Admin";
        } else if (total <= ownerThreshold) {
          badge.className = "badge amber";
          badge.textContent = "Perlu approval Owner/Admin";
        } else {
          badge.className = "badge red";
          badge.textContent = "Perlu approval Owner";
        }
      }
    }

    function bindRow(row) {
      $$("input, select", row).forEach((el) => {
        el.addEventListener("input", recalc);
        el.addEventListener("change", () => {
          updateRow(row);
          recalc();
        });
      });
    }

    $$(".po-row", rowsEl).forEach(bindRow);

    let rowIndex = $$(".po-row", rowsEl).length;

    $$("[data-add-row]").forEach((button) => {
      button.addEventListener("click", () => {
        if (!template) return;
        const html = template.innerHTML.replaceAll("__INDEX__", String(rowIndex++));
        rowsEl.insertAdjacentHTML("beforeend", html);
        const row = rowsEl.lastElementChild;
        bindRow(row);
        recalc();
      });
    });

    rowsEl?.addEventListener("click", (e) => {
      const remove = e.target.closest("[data-remove-row]");
      if (!remove) return;
      const row = remove.closest(".po-row");
      if ($$(".po-row", rowsEl).length > 1) {
        row.remove();
        recalc();
      }
    });

    [discountEl, taxEl, shippingEl].forEach((el) => el && el.addEventListener("input", recalc));
    recalc();
  }

  /* ---------- Init ---------- */
  document.addEventListener("DOMContentLoaded", () => {
    initBookingForm();
    initPos();
    initCash();
    initPoForm();
  });
})();
