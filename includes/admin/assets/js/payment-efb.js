/**
 * Easy Form Builder — Payment Management Admin Page
 * Provides a data-table view of all payment transactions with filtering,
 * pagination, detail view, refund, and cancel-subscription actions.
 *
 * Depends on: efb_payment (localized from PHP)
 */
(function () {
  "use strict";

  const T = efb_payment.text;
  const FORMS = efb_payment.forms || [];
  const IS_RTL = efb_payment.rtl;

  /* ── State ─────────────────────────────────────── */
  let currentPage = 1;
  let perPage = 20;
  let filterGateway = "";
  let filterStatus = "";
  let filterFormId = 0;
  let filterSearch = "";
  let totalRows = 0;
  let totalPages = 0;

  /* ── Helpers ───────────────────────────────────── */
  const $ = (sel, ctx) => (ctx || document).querySelector(sel);
  const $$ = (sel, ctx) => (ctx || document).querySelectorAll(sel);
  const esc = (s) => {
    const d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  };

  function gatewayBadge(gw) {
    const map = {
      paypal: { icon: "bi-paypal", color: "#003087", label: "PayPal" },
      stripe: { icon: "bi-credit-card-fill", color: "#635bff", label: "Stripe" },
      zarinpal: { icon: "bi-currency-exchange", color: "#ffc107", label: "ZarinPal" },
      persiapay: { icon: "bi-cash-stack", color: "#28a745", label: "PersiaPay" },
    };
    const m = map[gw] || { icon: "bi-wallet2", color: "#6c757d", label: gw || "—" };
    return `<span class="efb-pay-badge" style="--badge-color:${m.color}"><i class="efb bi ${m.icon}"></i> ${esc(m.label)}</span>`;
  }

  function statusBadge(st) {
    const map = {
      completed: "success",
      active: "success",
      pending: "warning",
      cancelled: "secondary",
      refunded: "info",
      failed: "danger",
      suspended: "secondary",
      expired: "secondary",
    };
    const cls = map[st] || "secondary";
    const label = T[st] || st || "—";
    return `<span class="efb-pay-status efb-pay-status--${cls}">${esc(label)}</span>`;
  }

  function intervalLabel(unit) {
    const map = { DAY: T.daily, WEEK: T.weekly, MONTH: T.monthly, YEAR: T.yearly, day: T.daily, week: T.weekly, month: T.monthly, year: T.yearly };
    return map[unit] || unit || "—";
  }

  function currency(amount, cur) {
    try {
      return Number(amount).toLocaleString(undefined, { style: "currency", currency: cur || "USD" });
    } catch {
      return amount + " " + (cur || "USD");
    }
  }

  function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
      showToast(T.copied, "success");
    });
  }

  function showToast(msg, type) {
    const toast = document.createElement("div");
    toast.className = `efb-pay-toast efb-pay-toast--${type || "info"}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add("show"), 10);
    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 300);
    }, 2500);
  }

  /* ── Render ────────────────────────────────────── */
  function render() {
    const root = $("#efb-payment-root");
    root.innerHTML = `
      <div class="efb-pay-wrap">
        <!-- Header -->
        <div class="efb-pay-header">
          <div class="efb-pay-header-left">
            <h2 class="efb-pay-title"><i class="efb bi bi-receipt-cutoff"></i> ${T.payments}</h2>
          </div>
          <div class="efb-pay-header-right">
            <button class="efb-pay-btn efb-pay-btn--outline" id="efb-pay-export-btn"><i class="efb bi bi-download"></i> ${T.export}</button>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="efb-pay-stats" id="efb-pay-stats"></div>

        <!-- Filters -->
        <div class="efb-pay-filters">
          <div class="efb-pay-search-box">
            <i class="efb bi bi-search"></i>
            <input type="text" id="efb-pay-search" placeholder="${T.search}..." class="efb-pay-input" />
          </div>
          <select id="efb-pay-filter-gateway" class="efb-pay-select">
            <option value="">${T.allGateways}</option>
            <option value="paypal">PayPal</option>
            <option value="stripe">Stripe</option>
            <option value="zarinpal">ZarinPal</option>
            <option value="persiapay">PersiaPay</option>
          </select>
          <select id="efb-pay-filter-status" class="efb-pay-select">
            <option value="">${T.allStatuses}</option>
            <option value="completed">${T.completed}</option>
            <option value="active">${T.active}</option>
            <option value="pending">${T.pending}</option>
            <option value="refunded">${T.refunded}</option>
            <option value="cancelled">${T.cancelled}</option>
            <option value="failed">${T.failed}</option>
          </select>
          <select id="efb-pay-filter-form" class="efb-pay-select">
            <option value="0">${T.allForms}</option>
            ${FORMS.map((f) => `<option value="${f.form_id}">${esc(f.form_name)} (#${f.form_id})</option>`).join("")}
          </select>
        </div>

        <!-- Table -->
        <div class="efb-pay-table-wrap">
          <table class="efb-pay-table">
            <thead>
              <tr>
                <th>#</th>
                <th>${T.trackCode}</th>
                <th>${T.gateway}</th>
                <th>${T.paymentType}</th>
                <th>${T.amount}</th>
                <th>${T.status}</th>
                <th>${T.user}</th>
                <th>${T.formName}</th>
                <th>${T.date}</th>
                <th>${T.actions}</th>
              </tr>
            </thead>
            <tbody id="efb-pay-tbody">
              <tr><td colspan="10" class="efb-pay-center">${T.loading}</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="efb-pay-pagination" id="efb-pay-pagination"></div>
      </div>`;

    // Bind events
    bindEvents();
    loadPayments();
  }

  function bindEvents() {
    let searchTimer;
    $("#efb-pay-search").addEventListener("input", (e) => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        filterSearch = e.target.value.trim();
        currentPage = 1;
        loadPayments();
      }, 400);
    });

    $("#efb-pay-filter-gateway").addEventListener("change", (e) => {
      filterGateway = e.target.value;
      currentPage = 1;
      loadPayments();
    });

    $("#efb-pay-filter-status").addEventListener("change", (e) => {
      filterStatus = e.target.value;
      currentPage = 1;
      loadPayments();
    });

    $("#efb-pay-filter-form").addEventListener("change", (e) => {
      filterFormId = parseInt(e.target.value) || 0;
      currentPage = 1;
      loadPayments();
    });

    $("#efb-pay-export-btn").addEventListener("click", exportCSV);
  }

  /* ── API ───────────────────────────────────────── */
  function loadPayments() {
    const tbody = $("#efb-pay-tbody");
    if (tbody) tbody.innerHTML = `<tr><td colspan="10" class="efb-pay-center"><div class="efb-payment-spinner small"></div></td></tr>`;

    const fd = new FormData();
    fd.append("action", "efb_get_payments");
    fd.append("nonce", efb_payment.nonce);
    fd.append("page", currentPage);
    fd.append("per_page", perPage);
    fd.append("gateway", filterGateway);
    fd.append("status", filterStatus);
    fd.append("form_id", filterFormId);
    fd.append("search", filterSearch);

    fetch(efb_payment.ajax_url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((res) => {
        if (res.success) {
          totalRows = res.data.total;
          totalPages = res.data.pages;
          renderStats(res.data.stats);
          renderTable(res.data.rows);
          renderPagination();
        } else {
          tbody.innerHTML = `<tr><td colspan="10" class="efb-pay-center text-danger">${T.error}</td></tr>`;
        }
      })
      .catch((err) => {
        console.error("[EFB] Load payments error:", err);
        tbody.innerHTML = `<tr><td colspan="10" class="efb-pay-center text-danger">${T.error}</td></tr>`;
      });
  }

  function renderStats(stats) {
    const el = $("#efb-pay-stats");
    if (!el || !stats) return;
    el.innerHTML = `
      <div class="efb-pay-stat-card">
        <div class="efb-pay-stat-icon"><i class="efb bi bi-receipt"></i></div>
        <div class="efb-pay-stat-info">
          <span class="efb-pay-stat-num">${stats.total_count || 0}</span>
          <span class="efb-pay-stat-label">${T.total} ${T.payments}</span>
        </div>
      </div>
      <div class="efb-pay-stat-card efb-pay-stat-card--success">
        <div class="efb-pay-stat-icon"><i class="efb bi bi-check-circle"></i></div>
        <div class="efb-pay-stat-info">
          <span class="efb-pay-stat-num">${stats.completed_count || 0}</span>
          <span class="efb-pay-stat-label">${T.completed}</span>
        </div>
      </div>
      <div class="efb-pay-stat-card efb-pay-stat-card--warning">
        <div class="efb-pay-stat-icon"><i class="efb bi bi-hourglass-split"></i></div>
        <div class="efb-pay-stat-info">
          <span class="efb-pay-stat-num">${stats.pending_count || 0}</span>
          <span class="efb-pay-stat-label">${T.pending}</span>
        </div>
      </div>
      <div class="efb-pay-stat-card efb-pay-stat-card--info">
        <div class="efb-pay-stat-icon"><i class="efb bi bi-arrow-return-left"></i></div>
        <div class="efb-pay-stat-info">
          <span class="efb-pay-stat-num">${stats.refund_count || 0}</span>
          <span class="efb-pay-stat-label">${T.refunded}</span>
        </div>
      </div>`;
  }

  function renderTable(rows) {
    const tbody = $("#efb-pay-tbody");
    if (!rows || rows.length === 0) {
      tbody.innerHTML = `<tr><td colspan="10" class="efb-pay-center efb-pay-empty">
        <i class="efb bi bi-inbox" style="font-size:2rem;opacity:.4"></i><br>${T.noPayments}</td></tr>`;
      return;
    }

    tbody.innerHTML = rows
      .map(
        (r, i) => `
      <tr class="efb-pay-row" data-id="${r.id}">
        <td class="efb-pay-cell-num">${(currentPage - 1) * perPage + i + 1}</td>
        <td>
          <span class="efb-pay-track" title="${esc(r.track)}" onclick="efbPayCopy('${esc(r.track)}')">${esc(r.track || "—")}</span>
        </td>
        <td>${gatewayBadge(r.gateway)}</td>
        <td>${r.payment_type === "subscription" || r.payment_type === "recurring"
          ? `<span class="efb-pay-type-sub"><i class="efb bi bi-arrow-repeat"></i> ${intervalLabel(r.interval_unit)}</span>`
          : `<span class="efb-pay-type-once"><i class="efb bi bi-lightning"></i> ${T.oneTime}</span>`}
        </td>
        <td class="efb-pay-amount">${currency(r.amount, r.currency)}</td>
        <td>${statusBadge(r.status)}</td>
        <td><span class="efb-pay-user" title="${esc(r.payer_email)}">${esc(r.payer_name || r.uid || "—")}</span></td>
        <td><span class="efb-pay-form-name">${esc(r.form_name || "#" + r.form_id)}</span></td>
        <td class="efb-pay-date">${r.created_at ? new Date(r.created_at).toLocaleDateString() : "—"}</td>
        <td class="efb-pay-actions-cell">
          <button class="efb-pay-action-btn" title="${T.viewDetails}" onclick="efbPayDetail(${r.id})"><i class="efb bi bi-eye"></i></button>
          ${r.gateway === "paypal" && r.payment_type !== "subscription" && r.status !== "refunded"
            ? `<button class="efb-pay-action-btn efb-pay-action-btn--danger" title="${T.refund}" onclick="efbPayRefund(${r.id})"><i class="efb bi bi-arrow-return-left"></i></button>`
            : ""}
          ${r.subscription_id && r.status === "active"
            ? `<button class="efb-pay-action-btn efb-pay-action-btn--warning" title="${T.cancelSub}" onclick="efbPayCancelSub(${r.id})"><i class="efb bi bi-x-circle"></i></button>`
            : ""}
        </td>
      </tr>`
      )
      .join("");
  }

  function renderPagination() {
    const el = $("#efb-pay-pagination");
    if (!el) return;
    if (totalPages <= 1) {
      el.innerHTML = `<span class="efb-pay-pag-info">${T.total}: ${totalRows}</span>`;
      return;
    }

    let btns = "";
    btns += `<button class="efb-pay-pag-btn" ${currentPage <= 1 ? "disabled" : ""} onclick="efbPayPage(${currentPage - 1})"><i class="efb bi bi-chevron-${IS_RTL ? "right" : "left"}"></i></button>`;
    const maxVisible = 5;
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);
    if (end - start < maxVisible - 1) start = Math.max(1, end - maxVisible + 1);

    if (start > 1) btns += `<button class="efb-pay-pag-btn" onclick="efbPayPage(1)">1</button>`;
    if (start > 2) btns += `<span class="efb-pay-pag-dots">…</span>`;
    for (let p = start; p <= end; p++) {
      btns += `<button class="efb-pay-pag-btn ${p === currentPage ? "active" : ""}" onclick="efbPayPage(${p})">${p}</button>`;
    }
    if (end < totalPages - 1) btns += `<span class="efb-pay-pag-dots">…</span>`;
    if (end < totalPages) btns += `<button class="efb-pay-pag-btn" onclick="efbPayPage(${totalPages})">${totalPages}</button>`;
    btns += `<button class="efb-pay-pag-btn" ${currentPage >= totalPages ? "disabled" : ""} onclick="efbPayPage(${currentPage + 1})"><i class="efb bi bi-chevron-${IS_RTL ? "left" : "right"}"></i></button>`;

    el.innerHTML = `
      <span class="efb-pay-pag-info">${T.page} ${currentPage} ${T.of} ${totalPages} (${totalRows})</span>
      <div class="efb-pay-pag-btns">${btns}</div>`;
  }

  /* ── Detail Modal ──────────────────────────────── */
  function showDetail(id) {
    const modal = $("#efb-payment-modal");
    const body = $("#efb-payment-modal-body");
    const title = $("#efb-payment-modal-title");
    const footer = $("#efb-payment-modal-footer");

    title.textContent = T.paymentDetails;
    body.innerHTML = `<div class="efb-pay-center"><div class="efb-payment-spinner small"></div></div>`;
    footer.innerHTML = "";
    modal.style.display = "flex";

    const fd = new FormData();
    fd.append("action", "efb_get_payment_detail");
    fd.append("nonce", efb_payment.nonce);
    fd.append("id", id);

    fetch(efb_payment.ajax_url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((res) => {
        if (!res.success) {
          body.innerHTML = `<p class="text-danger">${T.error}</p>`;
          return;
        }
        const r = res.data;
        body.innerHTML = `
          <div class="efb-pay-detail">
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.trackCode}</span><span class="efb-pay-detail-value efb-pay-copy" onclick="efbPayCopy('${esc(r.track)}')">${esc(r.track)}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.gateway}</span><span class="efb-pay-detail-value">${gatewayBadge(r.gateway)}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.paymentType}</span><span class="efb-pay-detail-value">${r.payment_type === "subscription" ? T.subscription + " (" + intervalLabel(r.interval_unit) + ")" : T.oneTime}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.amount}</span><span class="efb-pay-detail-value efb-pay-amount-lg">${currency(r.amount, r.currency)}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.status}</span><span class="efb-pay-detail-value">${statusBadge(r.status)}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.transactionId}</span><span class="efb-pay-detail-value efb-pay-copy" onclick="efbPayCopy('${esc(r.transaction_id)}')">${esc(r.transaction_id || "—")}</span></div>
            ${r.subscription_id ? `<div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.subscriptionId}</span><span class="efb-pay-detail-value efb-pay-copy" onclick="efbPayCopy('${esc(r.subscription_id)}')">${esc(r.subscription_id)}</span></div>` : ""}
            ${r.plan_id ? `<div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.planId}</span><span class="efb-pay-detail-value">${esc(r.plan_id)}</span></div>` : ""}
            ${r.capture_id ? `<div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.captureId}</span><span class="efb-pay-detail-value efb-pay-copy" onclick="efbPayCopy('${esc(r.capture_id)}')">${esc(r.capture_id)}</span></div>` : ""}
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.payerName}</span><span class="efb-pay-detail-value">${esc(r.payer_name || "—")}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.payerEmail}</span><span class="efb-pay-detail-value">${esc(r.payer_email || "—")}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.formName}</span><span class="efb-pay-detail-value">${esc(r.form_name || "#" + r.form_id)}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.ip}</span><span class="efb-pay-detail-value">${esc(r.ip || "—")}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.date}</span><span class="efb-pay-detail-value">${r.created_at}</span></div>
          </div>`;

        let footerBtns = `<button class="efb-pay-btn efb-pay-btn--secondary" onclick="efbPayCloseModal()">${T.close}</button>`;
        if (r.subscription_id && r.gateway === "paypal") {
          footerBtns += ` <button class="efb-pay-btn efb-pay-btn--primary" onclick="efbPaySubDetail(${r.id})"><i class="efb bi bi-info-circle"></i> ${T.subscriptionDetail}</button>`;
        }
        if (r.gateway === "paypal" && r.payment_type !== "subscription" && r.status !== "refunded") {
          footerBtns += ` <button class="efb-pay-btn efb-pay-btn--danger" onclick="efbPayRefund(${r.id})"><i class="efb bi bi-arrow-return-left"></i> ${T.refund}</button>`;
        }
        if (r.subscription_id && r.status === "active") {
          footerBtns += ` <button class="efb-pay-btn efb-pay-btn--warning" onclick="efbPayCancelSub(${r.id})"><i class="efb bi bi-x-circle"></i> ${T.cancelSub}</button>`;
        }
        footer.innerHTML = footerBtns;
      });
  }

  function showSubscriptionDetail(id) {
    const body = $("#efb-payment-modal-body");
    const title = $("#efb-payment-modal-title");
    title.textContent = T.subscriptionDetail;
    body.innerHTML = `<div class="efb-pay-center"><div class="efb-payment-spinner small"></div></div>`;

    const fd = new FormData();
    fd.append("action", "efb_get_subscription_detail");
    fd.append("nonce", efb_payment.nonce);
    fd.append("id", id);

    fetch(efb_payment.ajax_url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((res) => {
        if (!res.success) {
          body.innerHTML = `<p class="text-danger">${res.data || T.error}</p>`;
          return;
        }
        const s = res.data;
        const plan = s.plan_id || "—";
        const ppStatus = s.status || "—";
        const startTime = s.start_time || "—";
        const nextBilling = s.billing_info?.next_billing_time || "—";
        const lastPayment = s.billing_info?.last_payment?.time || "—";
        const lastAmount  = s.billing_info?.last_payment?.amount?.value || "—";
        const lastCur     = s.billing_info?.last_payment?.amount?.currency_code || "";
        const failedCount = s.billing_info?.failed_payments_count || 0;
        const subscriber  = s.subscriber?.email_address || "—";
        const subName     = (s.subscriber?.name?.given_name || "") + " " + (s.subscriber?.name?.surname || "");

        body.innerHTML = `
          <div class="efb-pay-detail">
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.subscriptionId}</span><span class="efb-pay-detail-value">${esc(s.id)}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.planId}</span><span class="efb-pay-detail-value">${esc(plan)}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.status}</span><span class="efb-pay-detail-value">${statusBadge(ppStatus.toLowerCase())}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">Start</span><span class="efb-pay-detail-value">${esc(startTime)}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.nextBilling}</span><span class="efb-pay-detail-value">${esc(nextBilling)}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">Last Payment</span><span class="efb-pay-detail-value">${lastAmount !== "—" ? currency(lastAmount, lastCur) : "—"} (${lastPayment})</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">Failed Payments</span><span class="efb-pay-detail-value">${failedCount}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.payerName}</span><span class="efb-pay-detail-value">${esc(subName.trim() || "—")}</span></div>
            <div class="efb-pay-detail-row"><span class="efb-pay-detail-label">${T.payerEmail}</span><span class="efb-pay-detail-value">${esc(subscriber)}</span></div>
          </div>`;
      });
  }

  /* ── Actions ───────────────────────────────────── */
  function doRefund(id) {
    if (!confirm(T.confirmRefund)) return;

    const fd = new FormData();
    fd.append("action", "efb_refund_payment");
    fd.append("nonce", efb_payment.nonce);
    fd.append("id", id);

    fetch(efb_payment.ajax_url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((res) => {
        if (res.success) {
          showToast(T.refundSuccess, "success");
          loadPayments();
          closeModal();
        } else {
          showToast(res.data || T.error, "danger");
        }
      })
      .catch(() => showToast(T.error, "danger"));
  }

  function doCancelSub(id) {
    if (!confirm(T.confirmCancel)) return;

    const fd = new FormData();
    fd.append("action", "efb_cancel_subscription");
    fd.append("nonce", efb_payment.nonce);
    fd.append("id", id);

    fetch(efb_payment.ajax_url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((res) => {
        if (res.success) {
          showToast(T.cancelSuccess, "success");
          loadPayments();
          closeModal();
        } else {
          showToast(res.data || T.error, "danger");
        }
      })
      .catch(() => showToast(T.error, "danger"));
  }

  function closeModal() {
    const modal = $("#efb-payment-modal");
    if (modal) modal.style.display = "none";
  }

  /* ── Export CSV ─────────────────────────────────── */
  function exportCSV() {
    // Fetch all rows (up to 10000)
    const fd = new FormData();
    fd.append("action", "efb_get_payments");
    fd.append("nonce", efb_payment.nonce);
    fd.append("page", 1);
    fd.append("per_page", 10000);
    fd.append("gateway", filterGateway);
    fd.append("status", filterStatus);
    fd.append("form_id", filterFormId);
    fd.append("search", filterSearch);

    fetch(efb_payment.ajax_url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((res) => {
        if (!res.success || !res.data.rows.length) {
          showToast(T.noPayments, "warning");
          return;
        }
        const header = ["ID", "Track", "Gateway", "Type", "Amount", "Currency", "Status", "Transaction ID", "Subscription ID", "Payer", "Email", "Form", "Date"];
        const csvRows = [header.join(",")];
        res.data.rows.forEach((r) => {
          csvRows.push(
            [r.id, r.track, r.gateway, r.payment_type, r.amount, r.currency, r.status, r.transaction_id, r.subscription_id, `"${(r.payer_name || "").replace(/"/g, '""')}"`, r.payer_email, `"${(r.form_name || "").replace(/"/g, '""')}"`, r.created_at].join(",")
          );
        });
        const blob = new Blob(["\uFEFF" + csvRows.join("\n")], { type: "text/csv;charset=utf-8;" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `efb-payments-${new Date().toISOString().split("T")[0]}.csv`;
        link.click();
      });
  }

  /* ── Global handlers (called from onclick) ─────── */
  window.efbPayPage = (p) => {
    currentPage = p;
    loadPayments();
  };
  window.efbPayDetail = (id) => showDetail(id);
  window.efbPayRefund = (id) => doRefund(id);
  window.efbPayCancelSub = (id) => doCancelSub(id);
  window.efbPaySubDetail = (id) => showSubscriptionDetail(id);
  window.efbPayCloseModal = () => closeModal();
  window.efbPayCopy = (text) => copyText(text);

  // Close modal on overlay click or close button
  document.addEventListener("click", (e) => {
    if (e.target.id === "efb-payment-modal") closeModal();
    if (e.target.id === "efb-payment-modal-close" || e.target.closest("#efb-payment-modal-close")) closeModal();
  });

  // Init
  document.addEventListener("DOMContentLoaded", render);
})();
