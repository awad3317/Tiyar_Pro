/**
 * Mursal Offline A4 Shipment Manifest Printer
 * يبني ويطبع كشف الشحنة الصادرة الرسمي (A4 Portrait - توأم كشف المحاسب 100%)
 * محلياً بالكامل عبر iframe.srcdoc بدون أي اتصال بالسيرفر (يعمل في وضع الطيران).
 */
(() => {
    const esc = (val) => {
        if (val === null || val === undefined) return '';
        return String(val)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    const formatMoney = (amount) => {
        const num = Number(amount || 0);
        return Math.round(num).toLocaleString('en-US');
    };

    const formatDateOnly = (isoOrStr) => {
        if (!isoOrStr) {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
        const str = String(isoOrStr);
        if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
            return str.substring(0, 10);
        }
        const d = new Date(str);
        if (isNaN(d.getTime())) return '—';
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    };

    const formatNowDateTime = () => {
        const d = new Date();
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        const hh = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
    };

    const statusLabelFor = (shipment) => {
        if (shipment?.status_label) return shipment.status_label;
        const map = {
            draft: 'قيد التجهيز',
            in_transit: 'في الطريق',
            partially_received: 'استلام جزئي',
            received: 'تم الاستلام بالكامل',
            cancelled: 'ملغاة',
        };
        return map[shipment?.status] || shipment?.status || 'في الطريق';
    };

    function buildHtml(shipment, officeInfo = {}) {
        const rawParcels = Array.isArray(shipment?.parcels) ? shipment.parcels : [];
        const activeParcels = rawParcels.filter((p) => String(p?.status || '') !== 'cancelled');
        const cancelledCount = rawParcels.length - activeParcels.length;

        const paidParcels = [];
        const deferredParcels = [];
        let totalPaidAmount = 0;
        let totalDeferredAmount = 0;
        const destinationsSet = new Set();

        activeParcels.forEach((p) => {
            const amt = Number(p?.cod_amount || 0);
            if (String(p?.payment_type || '') === 'deferred') {
                deferredParcels.push(p);
                totalDeferredAmount += amt;
            } else {
                paidParcels.push(p);
                totalPaidAmount += amt;
            }
            const dest = String(p?.destination_label || p?.destination || '').trim();
            if (dest) destinationsSet.add(dest);
        });

        if (activeParcels.length === 0) {
            totalPaidAmount = Number(shipment?.total_paid_amount || 0);
            totalDeferredAmount = Number(shipment?.total_deferred_amount || 0);
        }

        const totalGrandAmount = totalPaidAmount + totalDeferredAmount;
        let activeDestinations = Array.from(destinationsSet);
        if (activeDestinations.length === 0 && Array.isArray(shipment?.destinations_summary)) {
            activeDestinations = shipment.destinations_summary;
        }

        const senderOfficeName = String(officeInfo?.name || shipment?.office_name || 'المكتب').trim();
        const senderBranchName = String(officeInfo?.branch_name || shipment?.office_branch_name || '').trim();
        const senderOfficeLabel = senderBranchName ? `${senderOfficeName} - ${senderBranchName}` : senderOfficeName;
        const manifestDate = formatDateOnly(shipment?.dispatched_at || shipment?.created_at);
        const printDateTime = formatNowDateTime();
        const shipmentNumber = String(shipment?.shipment_number || '—');
        const driverName = String(shipment?.driver_name || '—');
        const driverPhone = String(shipment?.driver_phone || '—');
        const activeCount = activeParcels.length || Number(shipment?.parcels_count || 0);

        const rowsHtml = activeParcels.length > 0
            ? activeParcels.map((parcel, idx) => {
                const payLabel = parcel?.payment_label || (parcel?.payment_type === 'deferred' ? 'آجل' : 'محاسب');
                const destLabel = parcel?.destination_label || parcel?.destination || '—';
                const rowBg = idx % 2 === 1 ? 'background:#f8fafc;' : 'background:#ffffff;';
                return `<tr style="${rowBg}">
                    <td class="cell text-center font-mono">${idx + 1}</td>
                    <td class="cell font-mono">${esc(parcel?.receipt_number || '—')}</td>
                    <td class="cell font-bold">${esc(parcel?.recipient_name || 'بدون اسم')}</td>
                    <td class="cell font-mono dir-ltr text-right">${esc(parcel?.recipient_phone || '—')}</td>
                    <td class="cell">${esc(parcel?.package_type || 'طرد')}</td>
                    <td class="cell">${esc(destLabel)}</td>
                    <td class="cell text-center font-bold">${esc(payLabel)}</td>
                    <td class="cell font-mono font-bold text-left dir-ltr">${formatMoney(parcel?.cod_amount || 0)}</td>
                </tr>`;
            }).join('')
            : `<tr>
                <td colspan="8" class="cell text-center" style="padding:10px;color:#64748b;">لا توجد طرود في هذا الكشف</td>
            </tr>`;

        const cancelledNoticeHtml = cancelledCount > 0
            ? `<span style="font-size:10px;font-weight:700;color:#64748b;">* تم استبعاد (${cancelledCount}) طرد ملغي من الكشف والمجاميع</span>`
            : '';

        const notesHtml = shipment?.notes
            ? `<div class="print-avoid-break" style="margin-bottom:12px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;font-size:10px;">
                <span style="font-weight:800;color:#334155;">ملاحظات: </span>
                <span style="color:#334155;">${esc(shipment.notes)}</span>
            </div>`
            : '';

        return `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>كشف شحنة صادرة #${esc(shipmentNumber)} - ${esc(senderOfficeName)}</title>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        html, body {
            height: auto !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #0f172a;
            font-family: 'Almarai', 'Segoe UI', Tahoma, sans-serif;
            font-size: 11px;
            line-height: 1.375;
            direction: rtl;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .dir-ltr { direction: ltr; display: inline-block; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .font-bold { font-weight: 700; }
        .font-extrabold { font-weight: 800; }
        .font-black { font-weight: 900; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .header-bar {
            border-bottom: 2px solid #1e293b;
            padding-bottom: 12px;
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 12px;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
        }
        table.print-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #94a3b8;
            font-size: 10px;
        }
        table.print-table thead { display: table-header-group; }
        table.print-table tfoot { display: table-footer-group; }
        table.print-table tr, .print-avoid-break {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        table.print-table th {
            background: #e2e8f0;
            color: #0f172a;
            font-weight: 800;
            border: 1px solid #94a3b8;
            padding: 6px 8px;
        }
        table.print-table td.cell {
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
        }
        table.print-table tfoot td {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: 900;
            border: 1px solid #94a3b8;
            padding: 6px 8px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            text-align: center;
        }
        .summary-box {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px;
            background: #f8fafc;
        }
        .summary-box-grand {
            border: 2px solid #1e293b;
            border-radius: 8px;
            padding: 8px;
            background: #f1f5f9;
        }
        .signatures-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 32px;
            padding-top: 16px;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            width: 176px;
            margin: 0 auto;
            padding-top: 4px;
            font-size: 10px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <section style="width:100%;background:#ffffff;color:#0f172a;">
        <!-- 1. ترويسة الكشف -->
        <div class="header-bar">
            <div>
                <p style="margin:0;font-size:11px;font-weight:700;color:#475569;">${esc(senderOfficeLabel)}</p>
                <h1 style="margin:2px 0 0 0;font-size:18px;font-weight:900;color:#0f172a;">كشف شحنة صادرة</h1>
            </div>
            <div class="text-left" style="line-height:1.45;">
                <p style="margin:0;font-weight:700;">
                    <span>رقم الكشف: </span>
                    <span class="font-mono font-black">#${esc(shipmentNumber)}</span>
                </p>
                <p style="margin:0;">
                    <span>تاريخ الكشف: </span>
                    <span class="font-mono font-bold dir-ltr">${esc(manifestDate)}</span>
                </p>
                <p style="margin:0;">
                    <span>الحالة: </span>
                    <span class="font-extrabold">${esc(statusLabelFor(shipment))}</span>
                </p>
            </div>
        </div>

        <!-- 2. بيانات السائق والمصدر -->
        <div class="info-grid">
            <div>
                <span style="color:#64748b;">السائق: </span>
                <strong class="font-extrabold">${esc(driverName)}</strong>
            </div>
            <div>
                <span style="color:#64748b;">جوال السائق: </span>
                <strong class="font-mono font-bold dir-ltr">${esc(driverPhone)}</strong>
            </div>
            <div>
                <span style="color:#64748b;">إجمالي الطرود: </span>
                <strong class="font-mono font-black">${activeCount}</strong>
                <span style="font-size:10px;color:#475569;">(${deferredParcels.length} آجل / ${paidParcels.length} محاسب)</span>
            </div>
            <div>
                <span style="color:#64748b;">الفرع المرسل: </span>
                <strong class="font-bold">${esc(senderOfficeLabel)}</strong>
            </div>
            <div>
                <span style="color:#64748b;">الوجهات: </span>
                <strong class="font-bold">${activeDestinations.length > 0 ? esc(activeDestinations.join('، ')) : '—'}</strong>
            </div>
            <div>
                <span style="color:#64748b;">تاريخ الطباعة: </span>
                <strong class="font-mono dir-ltr">${esc(printDateTime)}</strong>
            </div>
        </div>

        <!-- 3. جدول الطرود الشامل (8 أعمدة مضغوطة مطابقة لكشف المحاسب تماماً) -->
        <div style="margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                <h2 style="margin:0;font-size:12px;font-weight:900;color:#0f172a;">أولاً: بيان الطرود المدرجة في الكشف</h2>
                ${cancelledNoticeHtml}
            </div>
            <table class="print-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width:32px;padding:6px 4px;">#</th>
                        <th class="text-right">رقم السند</th>
                        <th class="text-right">اسم المستلم</th>
                        <th class="text-right">رقم الجوال</th>
                        <th class="text-right">النوع</th>
                        <th class="text-right">الوجهة</th>
                        <th class="text-center" style="width:64px;">الدفع</th>
                        <th class="text-left" style="width:96px;">المبلغ (ر.ي)</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7" class="text-left">الإجمالي الكلي للطرود:</td>
                        <td class="font-mono text-left dir-ltr">${formatMoney(totalGrandAmount)} ر.ي</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        ${notesHtml}

        <!-- 4. الملخص النهائي والتوقيعات (3 مربعات مالية + عمودان للتوقيع) -->
        <div class="print-avoid-break" style="border-top:2px solid #1e293b;padding-top:12px;margin-top:12px;">
            <div class="summary-grid">
                <div class="summary-box">
                    <p style="margin:0;font-size:10px;color:#475569;font-weight:700;">إجمالي طرود الآجل</p>
                    <p class="font-mono font-black dir-ltr" style="margin:2px 0 0 0;font-size:12px;">${formatMoney(totalDeferredAmount)} ر.ي</p>
                </div>
                <div class="summary-box">
                    <p style="margin:0;font-size:10px;color:#475569;font-weight:700;">إجمالي طرود المحاسب</p>
                    <p class="font-mono font-black dir-ltr" style="margin:2px 0 0 0;font-size:12px;">${formatMoney(totalPaidAmount)} ر.ي</p>
                </div>
                <div class="summary-box-grand">
                    <p style="margin:0;font-size:10px;color:#1e293b;font-weight:800;">الإجمالي الكلي</p>
                    <p class="font-mono font-black dir-ltr" style="margin:2px 0 0 0;font-size:14px;">${formatMoney(totalGrandAmount)} ر.ي</p>
                </div>
            </div>

            <div class="signatures-grid">
                <div>
                    <p style="margin:0 0 24px 0;">توقيع الموظف</p>
                    <p class="sig-line">${esc(senderOfficeName)}</p>
                </div>
                <div>
                    <p style="margin:0 0 24px 0;">توقيع السائق / المستلم</p>
                    <p class="sig-line">${esc(driverName !== '—' ? driverName : '')}</p>
                </div>
            </div>
        </div>
    </section>
</body>
</html>`;
    }

    function print(shipment, officeInfo = {}) {
        if (!shipment) return false;
        const html = buildHtml(shipment, officeInfo);

        let iframe = document.getElementById('sent-shipment-offline-print-iframe');
        if (iframe) {
            iframe.remove();
        }

        iframe = document.createElement('iframe');
        iframe.id = 'sent-shipment-offline-print-iframe';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';

        iframe.onload = () => {
            setTimeout(() => {
                try {
                    if (iframe.contentWindow) {
                        iframe.contentWindow.focus();
                        iframe.contentWindow.print();
                    }
                } catch (err) {
                    console.warn('Offline manifest print error:', err);
                }
            }, 80);
        };

        document.body.appendChild(iframe);
        iframe.srcdoc = html;
        return true;
    }

    window.MursalManifestPrint = {
        buildHtml,
        print,
    };
    window.printShipmentOffline = print;
})();
