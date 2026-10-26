/* ===========================================================
   SmashBook — helper JS untuk prototipe
   Hanya untuk demontrasi antarmuka (tanpa backend).
   =========================================================== */

/* ---- Format & util ---- */

const rupiah = (n) =>
  "Rp " + Number(n || 0).toLocaleString("id-ID", { maximumFractionDigits: 0 });

const angka = (n) => Number(n || 0).toLocaleString("id-ID", { maximumFractionDigits: 0 });

/* ---- Modal ---- */

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add("open");
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove("open");
}

document.addEventListener("click", (e) => {
  if (e.target.classList.contains("modal-mask")) e.target.classList.remove("open");
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    document.querySelectorAll(".modal-mask.open").forEach((m) => m.classList.remove("open"));
  }
});

/* ---- Tab / segmented toggle ----
   <div class="tabs" data-tabs="grupA"> <button data-tab="x">..</button>
   <div data-panel="grupA/x"> ... </div>                             */

document.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-tab]");
  if (!btn) return;
  const group = btn.closest("[data-tabs]")?.dataset.tabs || btn.dataset.group;
  if (!group) return;

  document
    .querySelectorAll(`[data-panel^="${group}/"]`)
    .forEach((p) => (p.style.display = p.dataset.panel === `${group}/${btn.dataset.tab}` ? "" : "none"));

  btn.parentElement.querySelectorAll("[data-tab]").forEach((b) => {
    b.classList.toggle("on", b === btn);
    if (b.classList.contains("pay")) b.classList.remove("on");
  });
  if (btn.classList.contains("pay") || btn.classList.contains("chip")) btn.classList.add("on");
});

/* ---- Filter chips (single select per grup) ---- */

document.addEventListener("click", (e) => {
  const chip = e.target.closest(".chip");
  if (!chip) return;
  const box = chip.parentElement;
  box.querySelectorAll(".chip").forEach((c) => c.classList.remove("on"));
  chip.classList.add("on");
});

/* ---- Papan jadwal: klik slot kosong → isi form booking ---- */

function pickSlot(el) {
  if (el.classList.contains("bk") || el.classList.contains("mb") || el.classList.contains("peak")) return;
  const court = el.dataset.court || "";
  const hour = el.dataset.hour || "";
  const lapangan = document.getElementById("b_lapangan");
  const mulai = document.getElementById("b_mulai");
  const durasi = document.getElementById("b_durasi");
  if (lapangan && court) lapangan.value = court;
  if (mulai && hour) mulai.value = hour;
  if (durasi) hitungBooking();
  openModal("modalBooking");
}

/* ---- Kalkulator booking (tarif peak / off-peak) ---- */

const TARIF = {
  "off-peak": 60000,
  prime: 90000,
  night: 75000,
  weekend: 100000,
};

function kategoriJam(hour, weekend) {
  if (weekend) return "weekend";
  if (hour >= 16 && hour < 22) return "prime";
  if (hour >= 22) return "night";
  return "off-peak";
}

function hitungBooking() {
  const mulai = parseInt(document.getElementById("b_mulai")?.value || "0", 10);
  const durasi = parseInt(document.getElementById("b_durasi")?.value || "1", 10);
  const weekend = document.getElementById("b_hari")?.value === "weekend";
  const member = document.getElementById("b_tipe")?.value === "member";
  const box = document.getElementById("b_rincian");
  if (!box || !mulai) return;

  let dasar = 0;
  let baris = "";
  for (let i = 0; i < durasi; i++) {
    const h = (mulai + i) % 24;
    const kat = kategoriJam(h, weekend);
    const tarif = TARIF[kat];
    dasar += tarif;
    baris += `<div class="sum"><span class="lbl">${String(h).padStart(2, "0")}:00–${String(
      h + 1
    ).padStart(2, "0")}:00 · ${kat}</span><span>${rupiah(tarif)}</span></div>`;
  }

  const diskon = member ? Math.round(dasar * 0.1) : 0;
  const total = dasar - diskon;

  box.innerHTML =
    baris +
    `<div class="sum"><span class="lbl">Subtotal</span><span>${rupiah(dasar)}</span></div>` +
    (diskon
      ? `<div class="sum"><span class="lbl">Diskon member 10%</span><span style="color:#b91c1c">− ${rupiah(
          diskon
        )}</span></div>`
      : "") +
    `<div class="sum big"><span>Total</span><span>${rupiah(total)}</span></div>`;

  const dp = document.getElementById("b_dp");
  if (dp) dp.value = angka(Math.round(total * 0.5));
  const tot = document.getElementById("b_total");
  if (tot) tot.value = angka(total);
}

/* ---- POS: keranjang ---- */

const KERANJANG = [
  { nm: "Air Mineral 600ml", qty: 3, harga: 5000 },
  { nm: "Shuttlecock Yonex AS-30", qty: 1, harga: 45000 },
  { nm: "Sewa Raket (per jam)", qty: 2, harga: 15000 },
];

function renderKeranjang() {
  const box = document.getElementById("cartItems");
  if (!box) return;

  if (!KERANJANG.length) {
    box.innerHTML = `<div class="empty"><span class="ic">🛒</span>Keranjang masih kosong</div>`;
  } else {
    box.innerHTML = KERANJANG.map(
      (it, i) => `
      <div class="cart-item">
        <div>
          <div class="nm">${it.nm}</div>
          <div class="meta">${rupiah(it.harga)} × ${it.qty}</div>
        </div>
        <div class="amt">${rupiah(it.harga * it.qty)}</div>
        <div class="row" style="grid-column:1/-1;justify-content:space-between">
          <div class="qty">
            <button onclick="ubahQty(${i},-1)">−</button>
            <span>${it.qty}</span>
            <button onclick="ubahQty(${i},1)">+</button>
          </div>
          <button class="btn btn-sm btn-ghost" onclick="hapusItem(${i})">Hapus</button>
        </div>
      </div>`
    ).join("");
  }

  const subtotal = KERANJANG.reduce((a, b) => a + b.harga * b.qty, 0);
  const diskon = Number(document.getElementById("diskonVal")?.dataset.val || 0);
  const total = Math.max(subtotal - diskon, 0);

  setText("cartSubtotal", rupiah(subtotal));
  setText("cartDiskon", "− " + rupiah(diskon));
  setText("cartTotal", rupiah(total));
  setText("cartJumlah", KERANJANG.length + " item");

  const bayar = Number((document.getElementById("inputBayar")?.value || "").replace(/\D/g, ""));
  if (bayar) setText("cartKembali", rupiah(Math.max(bayar - total, 0)));
}

function ubahQty(i, d) {
  KERANJANG[i].qty += d;
  if (KERANJANG[i].qty <= 0) KERANJANG.splice(i, 1);
  renderKeranjang();
}

function hapusItem(i) {
  KERANJANG.splice(i, 1);
  renderKeranjang();
}

function tambahProduk(nm, harga) {
  const ada = KERANJANG.find((x) => x.nm === nm);
  if (ada) ada.qty++;
  else KERANJANG.push({ nm, qty: 1, harga });
  renderKeranjang();
}

function setText(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

/* ---- Kas: kalkulator selisih ---- */

function hitungSelisih() {
  const expected = Number(document.getElementById("expectedVal")?.dataset.val || 0);
  const input = document.getElementById("fisikInput");
  if (!input) return;
  const fisik = Number(input.value.replace(/\D/g, "") || 0);
  const selisih = fisik - expected;
  const box = document.getElementById("selisihBox");
  if (!box) return;

  box.style.display = "";
  const warna = selisih === 0 ? "#047857" : Math.abs(selisih) <= 10000 ? "#b45309" : "#b91c1c";
  box.querySelector(".v").textContent = (selisih > 0 ? "+ " : selisih < 0 ? "− " : "") + rupiah(Math.abs(selisih));
  box.querySelector(".v").style.color = warna;
  box.querySelector(".ket").textContent =
    selisih === 0
      ? "Kas sesuai, aman untuk ditutup."
      : Math.abs(selisih) <= 10000
      ? "Selisih dalam batas toleransi (≤ Rp 10.000), tetap dicatat."
      : "Selisih melebihi toleransi — wajib catatan alasan & verifikasi owner.";
}

/* ---- Quick PO: hitung total item ---- */

function hitungPO() {
  const rows = document.querySelectorAll("[data-po-qty]");
  let total = 0;
  rows.forEach((r) => {
    const qty = Number(r.querySelector("[data-qty]").value.replace(/\D/g, "") || 0);
    const harga = Number(r.dataset.poQty);
    const amount = qty * harga;
    total += amount;
    const cell = r.querySelector("[data-amount]");
    if (cell) cell.textContent = rupiah(amount);
  });
  setText("poSubtotal", rupiah(total));
  setText("poTotal", rupiah(total));

  const th = 500000;
  const badge = document.getElementById("poApproval");
  if (badge) {
    badge.className = "badge " + (total > th ? "amber" : "green");
    badge.textContent = total > th ? "Perlu approval Owner" : "Auto-approve Admin";
  }
  return total;
}

/* ---- Cetak ---- */

function cetak() {
  window.print();
}

/* ---- Inisialisasi ---- */

document.addEventListener("DOMContentLoaded", () => {
  renderKeranjang();
  hitungBooking();
  hitungPO();
});
