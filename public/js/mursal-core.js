/**
 * Mursal Core PWA Engine
 * المحرك الأساسي لإدارة قواعد البيانات المحلية، طابور المزامنة، والاتصال
 * المسار: public/js/mursal-core.js
 */
(() => {
    const DB_NAME = 'MursalDB';
    const DB_VERSION = 10; // ترقية لإضافة جدول الطرود القادمة في الطريق (أوفلاين)

    // قائمة الجداول المطلوبة للنظام حالياً ومستقبلاً
    const STORES = {
        queue: 'syncQueue',                          // طابور عام لكل الموديولات
        parcels: 'parcels',                          // طرود واردة
        favorites: 'favoriteCustomers',              // العملاء المميزون (لكل مكتب)
        sentShipments: 'sent_shipments',             // الشحنات المرسلة (أوفلاين + أونلاين)
        scannableParcels: 'outgoing_scannable_parcels', // الطرود الصادرة الجاهزة للمسح أوفلاين
        shipmentQueue: 'shipment_sync_queue',        // طابور عمليات الشحنات الأوفلاين
        transitParcels: 'transit_parcels',           // الطرود القادمة في الطريق (أوفلاين + أونلاين)
    };

    const plain = (val) => JSON.parse(JSON.stringify(val));
    const nextSeq = () => performance.timeOrigin + performance.now();

    const normalizeDigitsAndScanCode = (raw) => {
        if (!raw) return '';
        const ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const cleaned = String(raw)
            .replace(/[٠-٩]/g, (d) => ar.indexOf(d))
            .replace(/[۰-۹]/g, (d) => fa.indexOf(d))
            .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '')
            .trim();

        const uuidMatch = cleaned.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
        if (uuidMatch) {
            return uuidMatch[0].toLowerCase();
        }
        return cleaned.replace(/^#+/, '').trim();
    };

    /* ------------------------------------------------------------------
     | 1. قاعدة البيانات المحلية العامة (IndexedDB)
     * ----------------------------------------------------------------*/
    const MursalDB = {
        db: null,
        async open() {
            if (this.db) return this;
            return new Promise((resolve, reject) => {
                const req = indexedDB.open(DB_NAME, DB_VERSION);

                req.onupgradeneeded = (e) => {
                    const db = req.result;
                    // 1. الجداول الحالية (دون أي مساس ببنيتها أو بياناتها)
                    if (!db.objectStoreNames.contains(STORES.queue)) {
                        db.createObjectStore(STORES.queue, { keyPath: 'seq' });
                    }
                    if (!db.objectStoreNames.contains(STORES.parcels)) {
                        db.createObjectStore(STORES.parcels, { keyPath: 'id' });
                    }
                    if (!db.objectStoreNames.contains(STORES.favorites)) {
                        db.createObjectStore(STORES.favorites, { keyPath: 'id' });
                    }

                    // 2. جداول الشحنات الأوفلاين الجديدة (إضافية فقط)
                    if (!db.objectStoreNames.contains(STORES.sentShipments)) {
                        const shipmentsStore = db.createObjectStore(STORES.sentShipments, { keyPath: 'local_key' });
                        shipmentsStore.createIndex('office_id', 'office_id', { unique: false });
                        shipmentsStore.createIndex('status', 'status', { unique: false });
                        shipmentsStore.createIndex('created_at', 'created_at', { unique: false });
                    }

                    if (!db.objectStoreNames.contains(STORES.scannableParcels)) {
                        const scannableStore = db.createObjectStore(STORES.scannableParcels, { keyPath: 'id' });
                        scannableStore.createIndex('office_id', 'office_id', { unique: false });
                        scannableStore.createIndex('receipt_number', 'receipt_number', { unique: false });
                    }

                    if (!db.objectStoreNames.contains(STORES.shipmentQueue)) {
                        const shipmentQueueStore = db.createObjectStore(STORES.shipmentQueue, { keyPath: 'op_id' });
                        shipmentQueueStore.createIndex('created_at', 'created_at', { unique: false });
                    }

                    // 3. جدول الطرود القادمة في الطريق (أوفلاين)
                    if (!db.objectStoreNames.contains(STORES.transitParcels)) {
                        const transitStore = db.createObjectStore(STORES.transitParcels, { keyPath: 'id' });
                        transitStore.createIndex('destination_office_id', 'destination_office_id', { unique: false });
                    }
                };

                req.onsuccess = () => {
                    this.db = req.result;
                    resolve(this);
                };
                req.onerror = () => reject(req.error);
            });
        },

        tx(storeName, mode, work) {
            return new Promise((resolve, reject) => {
                const transaction = this.db.transaction(storeName, mode);
                const request = work(transaction.objectStore(storeName));
                transaction.oncomplete = () => resolve(request?.result);
                transaction.onerror = () => reject(transaction.error);
                transaction.onabort = () => reject(transaction.error);
            });
        },

        async getAll(storeName) {
            return (await this.tx(storeName, 'readonly', (s) => s.getAll())) || [];
        },

        put(storeName, value) {
            return this.tx(storeName, 'readwrite', (s) => s.put(plain(value)));
        },

        putMany(storeName, values) {
            return this.tx(storeName, 'readwrite', (s) => {
                values.forEach((v) => s.put(plain(v)));
            });
        },

        delete(storeName, key) {
            return this.tx(storeName, 'readwrite', (s) => s.delete(key));
        },

        deleteMany(storeName, keys) {
            return this.tx(storeName, 'readwrite', (s) => {
                keys.forEach((k) => s.delete(k));
            });
        },

        clear(storeName) {
            return this.tx(storeName, 'readwrite', (s) => s.clear());
        },

        async saveBootstrapData(officeId, payload = {}) {
            await this.open();
            const numericOfficeId = Number(officeId || payload?.office?.id || 0);
            const serverShipments = Array.isArray(payload.shipments) ? payload.shipments : [];
            const serverScannable = Array.isArray(payload.scannable_parcels) ? payload.scannable_parcels : [];

            const existingShipments = await this.getAll(STORES.sentShipments);
            const queuedOps = await this.getAll(STORES.shipmentQueue);

            const serverKeys = new Set();
            const serverClientUuids = new Set();
            const pendingCancelRefs = new Set();

            queuedOps.forEach((op) => {
                if (op?.type === 'cancel_shipment') {
                    if (op.client_uuid) pendingCancelRefs.add(String(op.client_uuid).toLowerCase());
                    if (op.shipment_id) pendingCancelRefs.add(String(op.shipment_id));
                }
            });

            const normalizedServerShipments = serverShipments.map((s) => {
                const clientUuid = s.client_uuid ? String(s.client_uuid).toLowerCase() : null;
                const localKey = clientUuid || s.local_key || ('srv_' + s.id);
                serverKeys.add(localKey);
                if (clientUuid) serverClientUuids.add(clientUuid);

                const isPendingLocalCancel =
                    (clientUuid && pendingCancelRefs.has(clientUuid)) ||
                    (s.id && pendingCancelRefs.has(String(s.id)));

                return {
                    ...plain(s),
                    local_key: localKey,
                    office_id: Number(s.office_id || numericOfficeId),
                    status: isPendingLocalCancel ? 'cancelled' : s.status,
                    status_label: isPendingLocalCancel ? 'ملغاة' : s.status_label,
                    can_cancel: isPendingLocalCancel ? false : Boolean(s.can_cancel),
                    pending_sync: Boolean(isPendingLocalCancel),
                    sync_status: isPendingLocalCancel ? 'pending_sync' : 'synced',
                };
            });

            // الحفاظ على الشحنات المحلية التي أُنشئت أوفلاين ولم تتم مزامنتها مع السيرفر بعد
            const keysToDelete = [];
            existingShipments.forEach((localItem) => {
                if (numericOfficeId && Number(localItem.office_id) !== numericOfficeId) {
                    return;
                }
                const localUuid = localItem.client_uuid ? String(localItem.client_uuid).toLowerCase() : null;
                const isAlreadyOnServer = serverKeys.has(localItem.local_key) || (localUuid && serverClientUuids.has(localUuid));

                if (!localItem.pending_sync || isAlreadyOnServer) {
                    keysToDelete.push(localItem.local_key);
                }
            });

            if (keysToDelete.length > 0) {
                await this.deleteMany(STORES.sentShipments, keysToDelete);
            }
            if (normalizedServerShipments.length > 0) {
                await this.putMany(STORES.sentShipments, normalizedServerShipments);
            }

            // تحديث الطرود الجاهزة للمسح مع استبعاد أي طرد محجوز في شحنة أوفلاين معلقة
            const reservedOfflineParcelIds = new Set();
            queuedOps.forEach((op) => {
                if (op?.type === 'create_shipment') {
                    const ids = op?.payload?.parcel_ids || op?.parcel_ids || [];
                    if (Array.isArray(ids)) {
                        ids.forEach((id) => reservedOfflineParcelIds.add(String(id)));
                    }
                }
            });

            const existingScannable = await this.getAll(STORES.scannableParcels);
            const scannableToDelete = existingScannable
                .filter((p) => !numericOfficeId || Number(p.office_id) === numericOfficeId)
                .map((p) => p.id);

            if (scannableToDelete.length > 0) {
                await this.deleteMany(STORES.scannableParcels, scannableToDelete);
            }

            const filteredScannable = serverScannable
                .filter((p) => !reservedOfflineParcelIds.has(String(p.id)))
                .map((p) => ({
                    ...plain(p),
                    office_id: Number(p.office_id || numericOfficeId),
                }));

            if (filteredScannable.length > 0) {
                await this.putMany(STORES.scannableParcels, filteredScannable);
            }

            return this.getLocalShipments(numericOfficeId);
        },

        async getLocalShipments(officeId) {
            await this.open();
            const numericOfficeId = Number(officeId || 0);
            const all = await this.getAll(STORES.sentShipments);
            return all
                .filter((s) => !numericOfficeId || Number(s.office_id) === numericOfficeId)
                .sort((a, b) => {
                    if (Boolean(a.pending_sync) !== Boolean(b.pending_sync)) {
                        return a.pending_sync ? -1 : 1;
                    }
                    const tA = new Date(a.created_at || 0).getTime() || 0;
                    const tB = new Date(b.created_at || 0).getTime() || 0;
                    return tB - tA;
                });
        },

        async getScannableParcels(officeId) {
            await this.open();
            const numericOfficeId = Number(officeId || 0);
            const all = await this.getAll(STORES.scannableParcels);
            return all.filter((p) => !numericOfficeId || Number(p.office_id) === numericOfficeId);
        },

        async saveSyncedShipment(officeId, serverShipment, removedParcelIds = []) {
            await this.open();
            if (!serverShipment) return null;
            const numericOfficeId = Number(officeId || serverShipment.office_id || 0);
            const clientUuid = serverShipment.client_uuid ? String(serverShipment.client_uuid).toLowerCase() : null;
            const localKey = clientUuid || serverShipment.local_key || ('srv_' + serverShipment.id);

            const record = {
                ...plain(serverShipment),
                local_key: localKey,
                office_id: numericOfficeId,
                pending_sync: false,
                sync_status: 'synced',
            };
            await this.put(STORES.sentShipments, record);

            if (Array.isArray(removedParcelIds) && removedParcelIds.length > 0) {
                await this.deleteMany(STORES.scannableParcels, removedParcelIds.map(String));
            }

            return record;
        },

        async createOfflineShipment(officeId, data = {}) {
            await this.open();
            const numericOfficeId = Number(officeId || 0);
            const clientUuid = String(data.client_uuid || '').toLowerCase();
            const dispatchNow = data.dispatch_now !== false;
            const nowIso = new Date().toISOString();
            const rawParcels = Array.isArray(data.parcels) ? data.parcels : [];

            let totalPaid = 0;
            let totalDeferred = 0;
            const destinationsSet = new Set();

            const parcelsSnapshot = rawParcels.map((p) => {
                const amt = Number(p.cod_amount || 0);
                if (p.payment_type === 'deferred') {
                    totalDeferred += amt;
                } else {
                    totalPaid += amt;
                }
                const dest = String(p.destination_label || p.destination || '').trim();
                if (dest) destinationsSet.add(dest);

                return {
                    ...plain(p),
                    office_id: numericOfficeId,
                    destination: dest,
                    destination_label: dest,
                    status: dispatchNow ? 'in_transit' : 'pending',
                    status_label: dispatchNow ? 'في الطريق' : 'في الانتظار',
                    is_received: false,
                };
            });

            const shortSuffix = clientUuid.replace(/[^0-9a-f]/gi, '').slice(-4).toUpperCase() || String(Date.now()).slice(-4);
            const localShipment = {
                id: null,
                client_uuid: clientUuid,
                local_key: clientUuid,
                office_id: numericOfficeId,
                shipment_number: 'محلي-' + shortSuffix,
                driver_name: String(data.driver_name || '').trim(),
                driver_phone: String(data.driver_phone || '').trim(),
                parcels_count: parcelsSnapshot.length,
                received_parcels_count: 0,
                total_paid_amount: totalPaid,
                total_deferred_amount: totalDeferred,
                total_amount: totalPaid + totalDeferred,
                destinations_summary: Array.from(destinationsSet),
                status: dispatchNow ? 'in_transit' : 'draft',
                status_label: dispatchNow ? 'في الطريق' : 'في الانتظار',
                notes: data.notes ? String(data.notes).trim() : null,
                dispatched_at: dispatchNow ? nowIso : null,
                completed_at: null,
                cancelled_at: null,
                created_at: nowIso,
                pending_sync: true,
                sync_status: 'pending_sync',
                can_cancel: true,
                show_url: null,
                print_url: null,
                parcels: parcelsSnapshot,
            };

            await this.put(STORES.sentShipments, localShipment);

            const parcelIds = parcelsSnapshot.map((p) => String(p.id));
            if (parcelIds.length > 0) {
                await this.deleteMany(STORES.scannableParcels, parcelIds);
            }

            await this.enqueueShipmentOp({
                op_id: 'create_' + clientUuid,
                type: 'create_shipment',
                client_uuid: clientUuid,
                office_id: numericOfficeId,
                payload: {
                    client_uuid: clientUuid,
                    driver_name: localShipment.driver_name,
                    driver_phone: localShipment.driver_phone,
                    parcel_ids: parcelIds,
                    dispatch_now: dispatchNow,
                    notes: localShipment.notes,
                },
            });

            return localShipment;
        },

        async cancelOfflineShipment(officeId, shipment, enqueueSync = true) {
            await this.open();
            if (!shipment) return null;
            const numericOfficeId = Number(officeId || shipment.office_id || 0);
            const clientUuid = shipment.client_uuid ? String(shipment.client_uuid).toLowerCase() : null;
            const localKey = shipment.local_key || clientUuid || ('srv_' + shipment.id);
            const nowIso = new Date().toISOString();

            const restoredParcels = (Array.isArray(shipment.parcels) ? shipment.parcels : [])
                .filter((p) => p && String(p.status || '') !== 'cancelled')
                .map((p) => ({
                    ...plain(p),
                    office_id: numericOfficeId,
                    status: 'pending',
                    status_label: 'في الانتظار',
                    is_received: false,
                }));

            const updatedShipment = {
                ...plain(shipment),
                local_key: localKey,
                office_id: numericOfficeId,
                status: 'cancelled',
                status_label: 'ملغاة',
                cancelled_at: nowIso,
                can_cancel: false,
                pending_sync: Boolean(enqueueSync),
                sync_status: enqueueSync ? 'pending_sync' : 'synced',
                parcels: restoredParcels,
            };

            await this.put(STORES.sentShipments, updatedShipment);

            if (restoredParcels.length > 0) {
                await this.putMany(STORES.scannableParcels, restoredParcels);
            }

            if (enqueueSync) {
                await this.enqueueShipmentOp({
                    op_id: 'cancel_' + (clientUuid || shipment.id) + '_' + Date.now(),
                    type: 'cancel_shipment',
                    client_uuid: clientUuid,
                    shipment_id: shipment.id ? String(shipment.id) : null,
                    office_id: numericOfficeId,
                    payload: {
                        shipment_id: shipment.id ? String(shipment.id) : null,
                        client_uuid: clientUuid,
                    },
                });
            }

            return {
                shipment: updatedShipment,
                restoredParcels,
            };
        },

        async findScannableParcel(officeId, codeOrReceipt) {
            await this.open();
            const normalized = normalizeDigitsAndScanCode(codeOrReceipt);
            if (!normalized) return null;

            const numericOfficeId = Number(officeId || 0);
            const all = await this.getAll(STORES.scannableParcels);

            for (let i = 0; i < all.length; i++) {
                const p = all[i];
                if (numericOfficeId && Number(p.office_id) !== numericOfficeId) continue;

                if (String(p.id || '').toLowerCase() === normalized.toLowerCase()) {
                    return p;
                }
                const receipt = normalizeDigitsAndScanCode(p.receipt_number);
                if (receipt && receipt === normalized) {
                    return p;
                }
            }

            return null;
        },

        async enqueueShipmentOp(op) {
            await this.open();
            const entry = {
                op_id: String(op?.op_id || ('ship_op_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8))),
                type: String(op?.type || op?.action || ''),
                client_uuid: op?.client_uuid ? String(op.client_uuid).toLowerCase() : null,
                shipment_id: op?.shipment_id ? String(op.shipment_id) : null,
                parcel_id: op?.parcel_id ? String(op.parcel_id) : null,
                office_id: op?.office_id ? Number(op.office_id) : null,
                payload: op?.payload ? plain(op.payload) : null,
                created_at: op?.created_at || new Date().toISOString(),
            };
            await this.put(STORES.shipmentQueue, entry);
            return entry;
        },

        _isFlushingShipments: false,

        async flushShipmentQueue(onSessionExpired) {
            await this.open();
            if (this._isFlushingShipments || !navigator.onLine) {
                return { ok: false, skipped: true };
            }

            const queue = (await this.getAll(STORES.shipmentQueue))
                .sort((a, b) => new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime());

            if (!queue.length) {
                return { ok: true, synced: 0, results: [] };
            }

            this._isFlushingShipments = true;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('/office/shipments/sent/sync', {
                    method: 'POST',
                    cache: 'no-store',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token || '',
                    },
                    body: JSON.stringify({ operations: queue }),
                });

                if (res.status === 401 || res.status === 419) {
                    if (typeof onSessionExpired === 'function') onSessionExpired();
                    return { ok: false, authExpired: true };
                }

                if (!res.ok) {
                    return { ok: false, status: res.status };
                }

                const data = await res.json();
                const results = Array.isArray(data?.results) ? data.results : [];

                for (const item of results) {
                    if (item.status === 'synced' || item.status === 'conflict') {
                        await this.delete(STORES.shipmentQueue, item.op_id);
                    }

                    if (item.shipment) {
                        const s = item.shipment;
                        const clientUuid = s.client_uuid ? String(s.client_uuid).toLowerCase() : (item.client_uuid ? String(item.client_uuid).toLowerCase() : null);
                        const localKey = clientUuid || s.local_key || ('srv_' + s.id);

                        // حذف أي مفتاح قديم مؤقت إن اختلف عن localKey النهائي
                        if (item.client_uuid && item.client_uuid !== localKey) {
                            await this.delete(STORES.sentShipments, item.client_uuid);
                        }

                        await this.put(STORES.sentShipments, {
                            ...plain(s),
                            local_key: localKey,
                            pending_sync: false,
                        });
                    } else if (item.status === 'conflict' && item.type === 'create_shipment' && item.client_uuid) {
                        await this.delete(STORES.sentShipments, String(item.client_uuid).toLowerCase());
                    }
                }

                if (Array.isArray(data?.scannable_parcels)) {
                    const remainingOps = await this.getAll(STORES.shipmentQueue);
                    const reservedIds = new Set();
                    remainingOps.forEach((op) => {
                        if (op?.type === 'create_shipment') {
                            const ids = op?.payload?.parcel_ids || [];
                            ids.forEach((id) => reservedIds.add(String(id)));
                        }
                    });

                    await this.clear(STORES.scannableParcels);
                    const nextScannable = data.scannable_parcels.filter((p) => !reservedIds.has(String(p.id)));
                    if (nextScannable.length > 0) {
                        await this.putMany(STORES.scannableParcels, nextScannable);
                    }
                }

                window.dispatchEvent(new CustomEvent('shipments-synced', { detail: data }));
                return data;
            } catch (err) {
                console.warn('MursalDB.flushShipmentQueue paused:', err);
                return { ok: false, error: err };
            } finally {
                this._isFlushingShipments = false;
            }
        },

        // ---------- إدارة الطرود القادمة في الطريق (أوفلاين) ----------

        async getPendingTransitReceiveIds() {
            await this.open();
            const queue = await this.getAll(STORES.queue);
            const pending = new Set();
            for (let i = 0; i < queue.length; i++) {
                const item = queue[i];
                if (item?.payload?._transit_parcel_id) {
                    pending.add(String(item.payload._transit_parcel_id));
                    continue;
                }
                const pathname = MursalSync.extractPathname(item?.endpoint || '');
                const m = pathname.match(/^\/office\/transit-parcels\/([^/?#]+)\/receive$/i);
                if (m && m[1]) {
                    pending.add(String(m[1]));
                }
            }
            return pending;
        },

        async saveTransitParcels(officeId, items = []) {
            await this.open();
            const numericOfficeId = Number(officeId || 0);
            const list = Array.isArray(items) ? items : [];
            const pendingReceiveIds = await this.getPendingTransitReceiveIds();

            const existing = await this.getAll(STORES.transitParcels);
            const keysToDelete = existing
                .filter((p) => !numericOfficeId || Number(p.destination_office_id) === numericOfficeId)
                .map((p) => String(p.id));

            if (keysToDelete.length > 0) {
                await this.deleteMany(STORES.transitParcels, keysToDelete);
            }

            const filtered = list
                .filter((p) => p && p.id && !pendingReceiveIds.has(String(p.id)))
                .map((p) => ({
                    ...plain(p),
                    id: String(p.id),
                    destination_office_id: Number(p.destination_office_id || numericOfficeId),
                }))
                .sort((a, b) => (new Date(b.dispatched_at || 0).getTime() || 0) - (new Date(a.dispatched_at || 0).getTime() || 0));

            if (filtered.length > 0) {
                await this.putMany(STORES.transitParcels, filtered);
            }

            if (numericOfficeId > 0) {
                try {
                    localStorage.setItem('mursal_transit_count_' + numericOfficeId, String(filtered.length));
                } catch (_) {}
            }
            window.dispatchEvent(new CustomEvent('transit-count-updated', { detail: { count: filtered.length } }));

            return filtered;
        },

        async getLocalTransitParcels(officeId) {
            await this.open();
            const numericOfficeId = Number(officeId || 0);
            const [all, pendingReceiveIds] = await Promise.all([
                this.getAll(STORES.transitParcels),
                this.getPendingTransitReceiveIds(),
            ]);

            return all
                .filter((p) => {
                    if (!p || !p.id) return false;
                    if (numericOfficeId && Number(p.destination_office_id) !== numericOfficeId) return false;
                    return !pendingReceiveIds.has(String(p.id));
                })
                .sort((a, b) => (new Date(b.dispatched_at || 0).getTime() || 0) - (new Date(a.dispatched_at || 0).getTime() || 0));
        },

        async removeLocalTransitParcel(officeId, parcelId) {
            await this.open();
            const idStr = String(parcelId || '');
            if (idStr) {
                await this.delete(STORES.transitParcels, idStr);
            }
            const remaining = await this.getLocalTransitParcels(officeId);
            const numericOfficeId = Number(officeId || 0);
            if (numericOfficeId > 0) {
                try {
                    localStorage.setItem('mursal_transit_count_' + numericOfficeId, String(remaining.length));
                } catch (_) {}
            }
            return remaining.length;
        },

        async receiveTransitParcelOffline(officeId, parcel, options = {}) {
            await this.open();
            if (!parcel || !parcel.id) return null;

            const numericOfficeId = Number(officeId || parcel.destination_office_id || 0);
            const parcelId = String(parcel.id);
            const channel = ['sms', 'whatsapp', 'both'].includes(options.channel) ? options.channel : 'both';
            const cleanSlot = options.slotNumber ? String(options.slotNumber).trim() : null;
            const endpoint = options.url
                ? MursalSync.extractPathname(options.url)
                : ('/office/transit-parcels/' + parcelId + '/receive');

            // 1. إزالة الطرد من قائمة "قادمة في الطريق" المحلية فوراً
            await this.delete(STORES.transitParcels, parcelId);

            // 2. إدراج نسخة محلية فورية في قائمة "الطرود المستلمة" (STORES.parcels) ليظهر فوراً أوفلاين
            const tempIncomingId = 'temp-transit-' + parcelId;
            const localIncomingParcel = {
                id: tempIncomingId,
                office_id: numericOfficeId || undefined,
                recipient_name: parcel.recipient_name_raw || (parcel.recipient_name && parcel.recipient_name !== 'بدون اسم' ? parcel.recipient_name : null),
                recipient_phone: String(parcel.recipient_phone || ''),
                package_type: String(parcel.package_type || 'طرد'),
                receipt_number: parcel.receipt_number ? String(parcel.receipt_number) : null,
                slot_number: cleanSlot || null,
                cod_amount: parcel.is_deferred ? Number(parcel.cod_amount_raw || 0) : null,
                source_office_id: parcel.source_office_id || null,
                source_office_name: parcel.source_office || null,
                source_region: parcel.source_region || null,
                status: 'in_office',
                created_at: new Date().toISOString(),
                is_local_temp: true,
            };
            await this.put(STORES.parcels, localIncomingParcel);

            // 3. جدولة طلب الاستلام في طابور المزامنة العام MursalSync
            await MursalSync.enqueue(endpoint, {
                channel,
                slot_number: cleanSlot || null,
                _transit_parcel_id: parcelId,
                _local_incoming_snapshot: localIncomingParcel,
            }, 'POST');

            const remaining = await this.getLocalTransitParcels(numericOfficeId);
            if (numericOfficeId > 0) {
                try {
                    localStorage.setItem('mursal_transit_count_' + numericOfficeId, String(remaining.length));
                } catch (_) {}
            }
            window.dispatchEvent(new CustomEvent('transit-count-updated', { detail: { count: remaining.length } }));
            window.dispatchEvent(new CustomEvent('mursal-queue-updated'));

            return {
                remainingCount: remaining.length,
                localIncomingParcel,
            };
        },

        async syncTransitFromServer(officeId = 0) {
            if (!navigator.onLine) return null;
            try {
                const res = await fetch('/office/transit-parcels?_t=' + Date.now(), {
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!res.ok) return null;
                const data = await res.json().catch(() => null);
                if (data && data.ok && Array.isArray(data.items)) {
                    return await this.saveTransitParcels(data.office_id || officeId, data.items);
                }
            } catch (_) {
                // العمل يستمر بصمت من IndexedDB
            }
            return null;
        }
    };

    const MursalShipmentStore = {
        saveBootstrapData: (officeId, payload) => MursalDB.saveBootstrapData(officeId, payload),
        getLocalShipments: (officeId) => MursalDB.getLocalShipments(officeId),
        getScannableParcels: (officeId) => MursalDB.getScannableParcels(officeId),
        findScannableParcel: (officeId, codeOrReceipt) => MursalDB.findScannableParcel(officeId, codeOrReceipt),
        saveSyncedShipment: (officeId, serverShipment, removedIds) => MursalDB.saveSyncedShipment(officeId, serverShipment, removedIds),
        createOfflineShipment: (officeId, data) => MursalDB.createOfflineShipment(officeId, data),
        cancelOfflineShipment: (officeId, shipment, enqueueSync) => MursalDB.cancelOfflineShipment(officeId, shipment, enqueueSync),
        enqueueShipmentOp: (op) => MursalDB.enqueueShipmentOp(op),
        flushShipmentQueue: (onSessionExpired) => MursalDB.flushShipmentQueue(onSessionExpired),
    };

    /* ------------------------------------------------------------------
     | 2. طابور المزامنة العام (Generic Sync Queue)
     * ----------------------------------------------------------------*/
   /* ------------------------------------------------------------------
     | 2. طابور المزامنة العام (Generic Sync Queue)
     * ----------------------------------------------------------------*/
    const MursalSync = {
        isSyncing: false,

        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        },

        extractPathname(endpoint) {
            const raw = String(endpoint || '').trim();
            if (!raw) return '';
            try {
                return new URL(raw, window.location.origin).pathname;
            } catch (_) {
                return raw.replace(/^https?:\/\/[^/]+/i, '');
            }
        },

        isCorruptEndpoint(endpoint) {
            const raw = String(endpoint || '').trim();
            if (!raw) return true;
            const pathname = this.extractPathname(raw);
            return (
                !pathname ||
                pathname.includes('temp_') ||
                pathname.includes('undefined') ||
                pathname.includes('null') ||
                pathname.includes(':')
            );
        },

        // تسجيل عملية لأي موديول في الطابور
        async enqueue(endpoint, payload, method = 'POST') {
            const cleanEndpoint = String(endpoint || '').trim();
            const upperMethod = String(method || 'POST').toUpperCase();

            // حماية صارمة: منع إدراج مهام DELETE أو PUT تحتوي على معرفات مؤقتة أو غير صالحة في المسار (pathname)
            if (upperMethod === 'DELETE' || upperMethod === 'PUT' || upperMethod === 'PATCH') {
                if (this.isCorruptEndpoint(cleanEndpoint)) {
                    console.warn('MursalSync: Blocked invalid enqueue endpoint:', cleanEndpoint);
                    return null;
                }
            }

            const entry = {
                seq: nextSeq(),
                endpoint: cleanEndpoint,
                method: upperMethod,
                payload: (payload && upperMethod !== 'DELETE' && upperMethod !== 'GET') ? plain(payload) : null,
                created_at: new Date().toISOString()
            };
            await MursalDB.put(STORES.queue, entry);

            // إشعار الـ Service Worker إن وجد
            if ('serviceWorker' in navigator && 'SyncManager' in window) {
                navigator.serviceWorker.ready.then((reg) => reg.sync.register('sync-parcels-queue')).catch(() => {});
            }

            return entry;
        },

        // معالجة الطابور بشكل عام
        async process(onSessionExpired) {
            if (this.isSyncing || !navigator.onLine) return;

            let queue = await MursalDB.getAll(STORES.queue);
            if (!queue || queue.length === 0) return;

            this.isSyncing = true;

            try {
                while (queue && queue.length > 0 && navigator.onLine) {
                    let aborted = false;

                    for (const item of queue) {
                        try {
                            const endpoint = String(item?.endpoint || '').trim();
                            const method = String(item?.method || 'POST').toUpperCase();

                            // 1. تنظيف أي مهمة فاسدة أو مؤقتة عالقة في مسار الرابط (pathname فقط، دون التأثر بـ http:// أو :port)
                            if (!item || this.isCorruptEndpoint(endpoint)) {
                                console.warn('MursalSync: Purging corrupt/temp queue item:', item);
                                await MursalDB.delete(STORES.queue, item.seq);
                                continue;
                            }

                            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                            const fetchOptions = {
                                method: method,
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token || ''
                                }
                            };

                            // لا نرسل body في طلبات DELETE أو GET أو HEAD، وننظّف الحقول الداخلية التي تبدأ بـ _
                            if (method !== 'GET' && method !== 'HEAD' && method !== 'DELETE' && item.payload !== null && item.payload !== undefined) {
                                let outgoingPayload = item.payload;
                                if (outgoingPayload && typeof outgoingPayload === 'object' && !Array.isArray(outgoingPayload)) {
                                    outgoingPayload = Object.fromEntries(
                                        Object.entries(outgoingPayload).filter(([k]) => !k.startsWith('_'))
                                    );
                                }
                                fetchOptions.body = JSON.stringify(outgoingPayload);
                            }

                            const res = await fetch(endpoint, fetchOptions);

                            if (res.status === 401 || res.status === 419) {
                                if (typeof onSessionExpired === 'function') onSessionExpired();
                                return;
                            }

                            const isDeleteAlreadyDone = method === 'DELETE' && (res.status === 404 || res.status === 410);
                            const isNonRetryable = (res.status === 400 || res.status === 422 || (method === 'PUT' && res.status === 404));

                            if (res.ok || isDeleteAlreadyDone) {
                                if (res.ok && item.payload?._transit_parcel_id) {
                                    const resData = await res.json().catch(() => ({}));
                                    const tempId = 'temp-transit-' + String(item.payload._transit_parcel_id);
                                    await MursalDB.delete(STORES.parcels, tempId).catch(() => {});

                                    // إذا عدّل الموظف حالة الطرد محلياً بعد استلامه أوفلاين، نربط التحديث اللاحق بالمعرّف الحقيقي من السيرفر
                                    if (resData && resData.incoming_id) {
                                        const allQueued = await MursalDB.getAll(STORES.queue);
                                        for (let qIdx = 0; qIdx < allQueued.length; qIdx++) {
                                            const qItem = allQueued[qIdx];
                                            if (qItem.seq === item.seq || !Array.isArray(qItem?.payload?.updates)) continue;
                                            let touched = false;
                                            const mappedUpdates = qItem.payload.updates.map((u) => {
                                                if (u && String(u.id) === tempId) {
                                                    touched = true;
                                                    return { ...u, id: resData.incoming_id };
                                                }
                                                return u;
                                            });
                                            if (touched) {
                                                await MursalDB.put(STORES.queue, {
                                                    ...qItem,
                                                    payload: { ...qItem.payload, updates: mappedUpdates },
                                                });
                                            }
                                        }
                                    }
                                }
                                await MursalDB.delete(STORES.queue, item.seq);
                            } else if (isNonRetryable) {
                                // خطأ في البيانات لا يمكن حله بإعادة المحاولة: نحذفه من الطابور حتى لا يعلق
                                if (item.payload?._transit_parcel_id) {
                                    await MursalDB.delete(STORES.parcels, 'temp-transit-' + String(item.payload._transit_parcel_id)).catch(() => {});
                                }
                                console.warn('MursalSync: Item unprocessable (' + res.status + '), removing from queue:', item);
                                await MursalDB.delete(STORES.queue, item.seq);
                            } else {
                                console.error('خطأ مزامنة السيرفر:', await res.json().catch(() => ({})));
                                aborted = true;
                                break;
                            }
                        } catch (err) {
                            console.warn('Network sync paused:', err);
                            aborted = true;
                            break;
                        }
                    }

                    if (aborted) break;

                    // فحص إن كانت هناك عمليات جديدة أضيفت للطابور أثناء انشغال المعالجة الحالية
                    queue = await MursalDB.getAll(STORES.queue);
                }
            } finally {
                this.isSyncing = false;
                window.dispatchEvent(new CustomEvent('mursal-queue-updated'));
            }
        }
    };
    /* ------------------------------------------------------------------
     | 3. الحالة الأساسية المشتركة لواجهات المكتب (officeShell)
     * ----------------------------------------------------------------*/
    function officeShell() {
        return {
            isOnline: navigator.onLine,
            isSyncing: false,
            authRequired: false,
            syncQueue: [], // 👈 إضافة هذا المتغير ليكون متاحاً في الهيدر بكل الصفحات
            authModal: {
                open: false,
            },

            // قائمة الإرسال التدريجي الحية (متاحة في كافة الصفحات عبر الزر الموحد +)
            streamingQueue: [],
            streamingIdCounter: 0,

            // حالة الإرسال السريع لرسائل SMS (كشف جملة)
            quickSms: {
                open: false,
                step: 'input',       // input | streaming
                rawText: '',
                items: [],
                isSending: false,
                successCount: 0,
                // حقول الإدخال التدريجي الجديدة
                phone: '',
                description: 'طرد',
                recipientName: '',
                codAmount: '',
                sourceRegion: '',
                slotNumber: '',
                channel: 'whatsapp', // افتراضياً واتساب مع دعم SMS أو كلاهما
            },

            // إشعار التنبيهات العائم الموحّد لجميع شاشات المكتب
            smsFeedback: {
                show: false,
                message: '',
                type: 'success',
                isError: false,
            },
            _feedbackTimer: null,

            openQuickSmsModal() {
                this.quickSms.open = true;
                this.quickSms.step = 'input';
                this.quickSms.phone = '';
                this.quickSms.description = 'طرد';
                this.quickSms.recipientName = '';
                this.quickSms.codAmount = '';
                this.quickSms.sourceRegion = '';
                this.quickSms.slotNumber = '';
                this.quickSms.channel = this.quickSms.channel || 'whatsapp';
                // autofocus على حقل الهاتف بعد فتح المودال
                this.$nextTick(() => {
                    const phoneInput = document.getElementById('streaming-phone-input');
                    if (phoneInput) phoneInput.focus();
                });
            },

            openQuickSms() {
                this.openQuickSmsModal();
            },

            resetQuickSms() {
                this.quickSms.open = false;
                this.quickSms.step = 'input';
                this.quickSms.rawText = '';
                this.quickSms.items = [];
                this.quickSms.isSending = false;
                this.quickSms.successCount = 0;
                this.quickSms.phone = '';
                this.quickSms.description = 'طرد';
                this.quickSms.recipientName = '';
                this.quickSms.codAmount = '';
                this.quickSms.sourceRegion = '';
                this.quickSms.slotNumber = '';
            },

            showFeedback(message, typeOrIsError = 'success') {
                if (!message) return;
                const normalizedType = typeof typeOrIsError === 'boolean'
                    ? (typeOrIsError ? 'error' : 'success')
                    : (['success', 'error', 'warning', 'info'].includes(String(typeOrIsError))
                        ? String(typeOrIsError)
                        : 'success');

                if (this._feedbackTimer) {
                    clearTimeout(this._feedbackTimer);
                    this._feedbackTimer = null;
                }

                this.smsFeedback = {
                    show: true,
                    message: String(message),
                    type: normalizedType,
                    isError: normalizedType === 'error',
                };

                this._feedbackTimer = setTimeout(() => {
                    if (this.smsFeedback) this.smsFeedback.show = false;
                    this._feedbackTimer = null;
                }, 3500);
            },

            dismissFeedback() {
                if (this._feedbackTimer) {
                    clearTimeout(this._feedbackTimer);
                    this._feedbackTimer = null;
                }
                if (this.smsFeedback) {
                    this.smsFeedback.show = false;
                }
            },

            async pasteFromClipboard() {
                try {
                    if (navigator.clipboard && navigator.clipboard.readText) {
                        const text = await navigator.clipboard.readText();
                        if (text) {
                            const current = (this.quickSms.rawText || '').trimEnd();
                            const combined = current ? current + '\n' + text.trim() : text.trim();
                            const matches = [...this.normalizeText(combined).matchAll(/(7[01378]\d{7})/g)];
                            if (matches.length > 15) {
                                const cutoffIndex = matches[15].index;
                                this.quickSms.rawText = combined.substring(0, cutoffIndex).trimEnd();
                                this.showFeedback('تم أخذ أول 15 طرداً فقط (الحد الأقصى لكل إرسالية).', true);
                            } else {
                                this.quickSms.rawText = combined;
                            }
                        }
                    } else {
                        this.showFeedback('خاصية اللصق التلقائي غير مدعومة في المتصفح، يرجى اللصق يدوياً.', true);
                    }
                } catch (err) {
                    console.warn('Clipboard read error:', err);
                    this.showFeedback('يرجى منح إذن الوصول للحافظة أو اللصق يدوياً في الحقل.', true);
                }
            },

            handleTextInput(event) {
                if (event && event.target && event.target.value !== undefined) {
                    this.quickSms.rawText = event.target.value;
                }
                const raw = this.quickSms?.rawText || '';
                const matches = [...this.normalizeText(raw).matchAll(/(7[01378]\d{7})/g)];
                if (matches.length > 15) {
                    const cutoffIndex = matches[15].index;
                    this.quickSms.rawText = raw.substring(0, cutoffIndex).trimEnd();
                    if (event && event.target) {
                        event.target.value = this.quickSms.rawText;
                    }
                    this.showFeedback('تم الوصول للحد الأقصى (15 طرداً) لكل إرسالية. يرجى إرسال الباقي في كشف منفصل.', true);
                }
            },

            appendTag(tag) {
                if (this.detectedParcelsCount >= 15) {
                    this.showFeedback('تم الوصول للحد الأقصى (15 طرداً) لكل إرسالية.', true);
                    return;
                }
                if (!this.quickSms.rawText || this.quickSms.rawText.trim() === '') {
                    this.quickSms.rawText = tag + '\n';
                    return;
                }
                const raw = this.quickSms.rawText;
                if (raw.endsWith('\n')) {
                    this.quickSms.rawText += tag + '\n';
                } else if (raw.endsWith(' ')) {
                    this.quickSms.rawText += tag + '\n';
                } else {
                    this.quickSms.rawText += ' ' + tag + '\n';
                }
                this.handleTextInput();
            },

            normalizeText(str) {
                if (!str) return '';
                const arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                let normalized = String(str)
                    .replace(/[٠-٩]/g, d => arabicDigits.indexOf(d))
                    .replace(/[۰-۹]/g, d => persianDigits.indexOf(d))
                    .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '');
                return normalized;
            },

            cleanDescription(raw) {
                if (!raw) return 'طرد';
                let cleaned = raw
                    .replace(/[\r\n]+/g, ' ')
                    .replace(/^[\s\-_,:،؛.\/\\#*~=+]+/g, '')
                    .replace(/[\s\-_,:،؛.\/\\#*~=+]+$/g, '')
                    .replace(/\s+/g, ' ')
                    .trim();
                return cleaned || 'طرد';
            },

            get detectedParcelsCount() {
                const text = this.normalizeText(this.quickSms?.rawText || '');
                return (text.match(/(7[01378]\d{7})/g) || []).length;
            },

            parseQuickList() {
                const raw = this.normalizeText(this.quickSms?.rawText || '');
                const phoneRegex = /(7[01378]\d{7})/g;
                const matches = [];
                let match;

                while ((match = phoneRegex.exec(raw)) !== null) {
                    matches.push({
                        phone: match[1],
                        start: match.index,
                        end: match.index + match[1].length
                    });
                }

                if (matches.length === 0) {
                    this.showFeedback('لم يتم العثور على أرقام هواتف يمنية صالحة (مثال: 779030449).', true);
                    return;
                }

                if (matches.length > 15) {
                    this.showFeedback('الحد الأقصى المسموح به هو 15 طرداً لكل إرسالية.', true);
                    return;
                }

                const items = [];
                for (let i = 0; i < matches.length; i++) {
                    const currentMatch = matches[i];
                    const nextMatch = matches[i + 1];

                    // استخراج الوصف بين نهاية الرقم الحالي وبداية الرقم التالي (أو نهاية النص)
                    const sliceStart = currentMatch.end;
                    const sliceEnd = nextMatch ? nextMatch.start : raw.length;
                    let desc = this.cleanDescription(raw.substring(sliceStart, sliceEnd));

                    // إذا كان طرد واحد فقط والوصف مكتوب قبل الرقم (مثال: "عسل 781152674")
                    if (matches.length === 1 && desc === 'طرد') {
                        const beforeDesc = this.cleanDescription(raw.substring(0, currentMatch.start));
                        if (beforeDesc && beforeDesc !== 'طرد') {
                            desc = beforeDesc;
                        }
                    }

                    items.push({
                        phone: currentMatch.phone,
                        description: desc || 'طرد',
                    });
                }

                this.quickSms.items = items;
                this.quickSms.step = 'preview';
            },

            async submitQuickSms() {
                if (this.quickSms.items.length === 0 || this.quickSms.items.length > 15 || this.quickSms.isSending) return;

                this.quickSms.isSending = true;
                const payload = { items: this.quickSms.items };
                const count = this.quickSms.items.length;
                const endpoint = '/office/parcels/quick-send-sms';

                try {
                    if (this.isOnline) {
                        try {
                            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                            const res = await fetch(endpoint, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token || ''
                                },
                                body: JSON.stringify(payload)
                            });
                            if (!res.ok) {
                                const data = await res.json().catch(() => ({}));
                                throw new Error(data.message || `تعذر الإرسال (رمز: ${res.status})`);
                            }
                            if (typeof this.refreshFromServer === 'function') {
                                await this.refreshFromServer();
                            }
                        } catch (err) {
                            console.warn('Online quick send failed, enqueuing to offline sync queue:', err);
                            if (window.MursalSync) {
                                await window.MursalSync.enqueue(endpoint, payload);
                                if (window.MursalDB) {
                                    this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                                }
                            }
                        }
                    } else {
                        if (window.MursalSync) {
                            await window.MursalSync.enqueue(endpoint, payload);
                            if (window.MursalDB) {
                                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                            }
                        }
                    }

                    this.quickSms.successCount = count;
                    this.quickSms.step = 'report';
                    this.showFeedback(`تم تسجيل وجدولة ${count} طرد بنجاح!`, false);
                } catch (error) {
                    console.error('Submit quick SMS error:', error);
                    this.showFeedback('حدث خطأ أثناء معالجة الرسائل السريعة.', true);
                } finally {
                    this.quickSms.isSending = false;
                }
            },

            async init() {
                // فتح قاعدة البيانات أولاً
                if (window.MursalDB) {
                    await window.MursalDB.open();
                    // قراءة حالة الطابور فور فتح أي صفحة
                    this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                }

                this.watchConnection(() => this.syncData());

                window.addEventListener('mursal-queue-updated', async () => {
                    if (window.MursalDB) {
                        this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                    }
                });

                // تحميل مسبق خفيف بالخلفية للطرود القادمة في الطريق لتكون جاهزة فوراً أوفلاين حتى قبل فتح الصفحة
                if (navigator.onLine && window.MursalDB && !window.location.pathname.startsWith('/office/transit-parcels')) {
                    try {
                        const lastBg = parseInt(sessionStorage.getItem('mursal_transit_bg_sync_at') || '0', 10) || 0;
                        if (Date.now() - lastBg > 45000) {
                            sessionStorage.setItem('mursal_transit_bg_sync_at', String(Date.now()));
                            setTimeout(() => {
                                if (navigator.onLine && window.MursalDB) {
                                    window.MursalDB.syncTransitFromServer();
                                }
                            }, 1200);
                        }
                    } catch (_) {}
                }

                // استقبال إشعار المزامنة التلقائية من الـ Service Worker
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.addEventListener('message', async (event) => {
                        if (event.data?.type === 'TRIGGER_SYNC') {
                            this.syncData();
                        }
                    });
                }
            },

            watchConnection(onReconnect = () => {}) {
                window.addEventListener('online', () => {
                    this.isOnline = true;
                    onReconnect();
                });
                window.addEventListener('offline', () => {
                    this.isOnline = false;
                });
            },

            // ---------- الإرسال التدريجي الفردي الموحد (Streaming Dispatch) ----------
            addToStreamingQueue(phone, description, recipientName = '', codAmount = null, sourceRegion = '', slotNumber = '') {
                const id = ++this.streamingIdCounter;
                const parsedCod = (codAmount !== null && codAmount !== '' && !isNaN(codAmount)) ? parseFloat(codAmount) : null;
                const cleanRegion = String(sourceRegion || this.quickSms?.sourceRegion || '').trim();
                const cleanSlot = String(slotNumber || '').trim();
                const entry = {
                    id,
                    phone,
                    description,
                    recipientName: (recipientName || '').trim(),
                    codAmount: (parsedCod && parsedCod > 0) ? parsedCod : null,
                    sourceRegion: cleanRegion || null,
                    slotNumber: cleanSlot || null,
                    channel: this.quickSms?.channel || 'whatsapp',
                    status: 'pending',   // pending | success | failed
                    message: '',
                    smsSent: false,
                    whatsappSent: false,
                };
                this.streamingQueue.unshift(entry);
                this.dispatchSingle(entry);
                return entry;
            },

            async dispatchSingle(entry) {
                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    
                    const url = (typeof ROUTES !== 'undefined' && ROUTES.quickSendSingle) 
                                ? ROUTES.quickSendSingle 
                                : '/office/parcels/quick-send-single';

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || ''
                        },
                        body: JSON.stringify({
                            phone: entry.phone,
                            description: entry.description,
                            recipient_name: entry.recipientName || null,
                            cod_amount: (entry.codAmount && entry.codAmount > 0) ? entry.codAmount : null,
                            source_region: entry.sourceRegion || null,
                            slot_number: entry.slotNumber || null,
                            channel: entry.channel || this.quickSms?.channel || 'whatsapp'
                        })
                    });

                    const data = await response.json().catch(() => ({}));

                    const targetIndex = this.streamingQueue.findIndex(item => item.id === entry.id);

                    if (targetIndex !== -1) {
                        if (response.ok && (data.success === true || data.status === 'success')) {
                            this.streamingQueue[targetIndex].status = 'success';
                            this.streamingQueue[targetIndex].smsSent = Boolean(data.sms_sent);
                            this.streamingQueue[targetIndex].whatsappSent = Boolean(data.whatsapp_sent);
                            this.streamingQueue[targetIndex].whatsappQueued = Boolean(data.whatsapp_queued);
                        } else {
                            this.streamingQueue[targetIndex].status = 'failed';
                            this.streamingQueue[targetIndex].error = data.message || 'فشل الإرسال';
                        }
                        
                        this.streamingQueue = [...this.streamingQueue];
                    }

                    // تحديث جدول الطرود بالصفحة إن وُجد
                    if (response.ok && typeof this.refreshFromServer === 'function') {
                        this.refreshFromServer();
                    }

                } catch (err) {
                    console.error('Streaming dispatch error:', err);
                    const targetIndex = this.streamingQueue.findIndex(item => item.id === entry.id);
                    if (targetIndex !== -1) {
                        this.streamingQueue[targetIndex].status = 'failed';
                        this.streamingQueue = [...this.streamingQueue];
                    }
                }
            },

            async retryStreamingEntry(entry) {
                entry.status = 'pending';
                entry.message = '';
                await this.dispatchSingle(entry);
            },

            get streamingSentCount() {
                return (this.streamingQueue || []).filter(e => e.status === 'success').length;
            },
            get streamingPendingCount() {
                return (this.streamingQueue || []).filter(e => e.status === 'pending').length;
            },
            get streamingFailedCount() {
                return (this.streamingQueue || []).filter(e => e.status === 'failed').length;
            },

            async syncData() {
                if (!navigator.onLine) {
                    window.requireOnlineAction();
                    return;
                }
                if (this.isSyncing) return;
                this.isSyncing = true;
                try {
                    // مزامنة الطابور وتحديث العداد في أي صفحة
                    if (window.MursalSync && window.MursalDB) {
                        await window.MursalSync.process(() => this.handleSessionExpired());
                        await window.MursalDB.flushShipmentQueue(() => this.handleSessionExpired());
                        this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                    }
                    if (typeof this.refreshFromServer === 'function') {
                        await this.refreshFromServer();
                    } else if (typeof window.__mursalPageRefresh === 'function') {
                        await window.__mursalPageRefresh();
                    } else if (navigator.onLine) {
                        window.location.reload();
                    }
                } catch (e) {
                    console.warn('Sync failed:', e);
                    window.notifyNetworkError('تعذر مزامنة البيانات مع الخادم، يرجى المحاولة مجدداً.');
                } finally {
                    this.isSyncing = false;
                    if (window.MursalDB) {
                        this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                    }
                }
            },

            handleSessionExpired() {
                this.authRequired = true;
                if (this.isSyncing !== undefined) this.isSyncing = false;
                this.authModal.open = true;
            },
        };
    }

    /* ------------------------------------------------------------------
     | 4. حارس الاتصال الموحّد والإشعار العائم للعمليات التي تتطلب إنترنت
     * ----------------------------------------------------------------*/
    const DEFAULT_OFFLINE_MSG = 'لا يوجد اتصال بالإنترنت، يرجى الاتصال بالإنترنت والمحاولة مجدداً.';
    const DEFAULT_NETWORK_ERR_MSG = 'تعذر الاتصال بالخادم، يرجى التأكد من الاتصال بالإنترنت والمحاولة مجدداً.';

    function requireOnlineAction(message = DEFAULT_OFFLINE_MSG) {
        if (!navigator.onLine) {
            window.dispatchEvent(new CustomEvent('office-toast', {
                detail: { message: message || DEFAULT_OFFLINE_MSG, type: 'error' }
            }));
            return false;
        }
        return true;
    }

    function notifyNetworkError(fallbackMessage = DEFAULT_NETWORK_ERR_MSG) {
        const msg = !navigator.onLine
            ? 'لا يوجد اتصال بالإنترنت، يرجى المحاولة مجدداً.'
            : (fallbackMessage || DEFAULT_NETWORK_ERR_MSG);
        window.dispatchEvent(new CustomEvent('office-toast', {
            detail: { message: msg, type: 'error' }
        }));
    }

    // حماية عامة لجميع نماذج POST/PUT/PATCH/DELETE القياسية (تتجاهل تلقائياً أي نموذج يدعم الأوفلاين عبر data-offline-capable="true")
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form || form.tagName !== 'FORM') return;
        if (form.getAttribute('data-offline-capable') === 'true') return;

        const rawMethod = (form.getAttribute('method') || form.method || 'GET').toUpperCase();
        const spoofedMethod = (form.querySelector('input[name="_method"]')?.value || '').toUpperCase();
        const effectiveMethod = spoofedMethod || rawMethod;

        if (effectiveMethod === 'GET') return;

        if (!navigator.onLine) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
            requireOnlineAction();
        }
    }, true);

    // إتاحة الأدوات والمكون الأساسي للـ Window
    window.MursalDB = MursalDB;
    window.MursalSync = MursalSync;
    window.MursalShipmentStore = MursalShipmentStore;
    window.MURSAL_STORES = STORES;
    window.officeShell = officeShell;
    window.requireOnlineAction = requireOnlineAction;
    window.notifyNetworkError = notifyNetworkError;
})();