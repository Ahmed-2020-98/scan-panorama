/**
 * Uploads files to the server in sequential chunks, so large DICOM archives
 * are not limited by PHP's upload_max_filesize.
 *
 * The queue lives in a global Alpine store, so uploads keep running while the
 * user moves to other pages (wire:navigate keeps the page's JavaScript alive)
 * and the floating upload tray shows progress from anywhere.
 */
const MAX_ATTEMPTS = 3;

const uuid = () =>
    window.crypto?.randomUUID?.() ??
    `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export const formatSize = (bytes) => {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }
    return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
};

async function errorMessage(response) {
    if (!response) return 'تعذر الاتصال بالخادم. تحقق من الإنترنت وحاول مرة أخرى.';
    if (response.status === 419) return 'انتهت الجلسة. حدّث الصفحة ثم أعد الرفع.';
    if (response.status === 403) return 'ليست لديك صلاحية رفع ملفات لهذه الحالة.';
    if (response.status === 413) return 'حجم الجزء أكبر من الحد المسموح على الخادم.';

    try {
        const data = await response.json();
        const errors = Object.values(data.errors ?? {}).flat();
        return errors[0] ?? data.message ?? 'حدث خطأ أثناء الرفع.';
    } catch {
        return 'حدث خطأ أثناء الرفع.';
    }
}

/** The global upload queue (Alpine.store('uploads')). */
export const uploadStore = {
    items: [],
    busy: false,
    open: true,

    get active() {
        return this.items.filter((item) => item.status === 'queued' || item.status === 'uploading');
    },
    get failed() {
        return this.items.filter((item) => item.status === 'error');
    },
    get done() {
        return this.items.filter((item) => item.status === 'done');
    },
    get uploadedBytes() {
        return this.items.reduce((sum, item) => sum + (item.status === 'done' ? item.size : item.status === 'uploading' ? (item.size * item.progress) / 100 : 0), 0);
    },
    get totalBytes() {
        return this.items.filter((item) => item.status !== 'cancelled').reduce((sum, item) => sum + item.size, 0);
    },
    get percent() {
        return this.totalBytes ? Math.floor((this.uploadedBytes * 100) / this.totalBytes) : 0;
    },

    add(fileList, config) {
        for (const file of Array.from(fileList ?? [])) {
            const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
            const item = {
                key: uuid(),
                file,
                name: file.name,
                size: file.size,
                progress: 0,
                status: 'queued',
                error: null,
                controller: null,
                ...config,
            };

            if (!config.extensions.includes(extension)) {
                item.status = 'error';
                item.error = `امتداد غير مسموح. المسموح: ${config.extensions.join('، ')}`;
            } else if (file.size === 0) {
                item.status = 'error';
                item.error = 'الملف فارغ.';
            } else if (file.size > config.maxBytes) {
                item.status = 'error';
                item.error = `حجم الملف أكبر من الحد المسموح (${formatSize(config.maxBytes)}).`;
            }

            if (item.status === 'error') item.file = null; // invalid: nothing to retry

            this.items.push(item);
        }

        this.open = true;
        this.run();
    },

    async run() {
        if (this.busy) return;
        this.busy = true;

        let item;
        while ((item = this.items.find((candidate) => candidate.status === 'queued'))) {
            if (await this.upload(item)) {
                window.Livewire?.dispatch('case-files-uploaded', { caseId: item.caseId });
            }
        }

        this.busy = false;
    },

    async upload(item) {
        item.status = 'uploading';
        item.controller = new AbortController();

        const total = Math.max(1, Math.ceil(item.size / item.chunkSize));
        const uploadId = (item.uploadId ??= uuid());

        for (let index = 0; index < total; index++) {
            const form = new FormData();
            form.append('type', item.type);
            form.append('upload_id', uploadId);
            form.append('chunk_index', index);
            form.append('total_chunks', total);
            form.append('file_name', item.name);
            form.append('file_size', item.size);
            form.append('chunk', item.file.slice(index * item.chunkSize, Math.min(item.size, (index + 1) * item.chunkSize)), 'chunk');

            let response = null;

            for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
                try {
                    response = await fetch(item.url, {
                        method: 'POST',
                        body: form,
                        credentials: 'same-origin',
                        signal: item.controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    // Only retry transient server/network failures
                    if (response.ok || response.status < 500) break;
                } catch (error) {
                    if (error.name === 'AbortError') {
                        item.status = 'cancelled';
                        item.error = 'تم إلغاء الرفع.';
                        return false;
                    }
                    response = null;
                }

                if (attempt < MAX_ATTEMPTS) await sleep(1000 * attempt);
            }

            if (!response || !response.ok) {
                item.status = 'error';
                item.error = await errorMessage(response);
                return false;
            }

            item.progress = Math.round(((index + 1) / total) * 100);

            if (index === total - 1) {
                const data = await response.json().catch(() => ({}));
                item.storedStatus = data.file?.status;
                if (!data.done) {
                    item.status = 'error';
                    item.error = 'لم يكتمل تجميع الملف على الخادم. أعد المحاولة.';
                    return false;
                }
            }
        }

        item.status = 'done';
        item.file = null; // free the memory of the local copy
        return true;
    },

    cancel(item) {
        if (item.status === 'queued') {
            item.status = 'cancelled';
            item.error = 'تم إلغاء الرفع.';
        }
        item.controller?.abort();
    },

    retry(item) {
        if (!item.file) return;
        item.error = null;
        item.status = 'queued';
        item.progress = 0;
        this.run();
    },

    retryFailed() {
        this.failed.filter((item) => item.file).forEach((item) => this.retry(item));
    },

    remove(item) {
        this.items = this.items.filter((candidate) => candidate !== item);
    },

    clearFinished() {
        this.items = this.items.filter((item) => item.status === 'queued' || item.status === 'uploading');
    },

    statusText(item) {
        return {
            queued: 'في الانتظار…',
            uploading: `جارٍ الرفع ${item.progress}%`,
            done: item.storedStatus === 'ready' ? 'تم الرفع بنجاح' : 'تم استلام الملف. جارٍ نقله إلى Google Drive…',
            error: item.error,
            cancelled: item.error,
        }[item.status];
    },

    formatSize,
};

/** Drop zone on a case page: adds files to the global queue and lists this case's uploads. */
export default function chunkUploader(config) {
    return {
        dragging: false,

        get items() {
            return this.$store.uploads.items.filter((item) => item.caseId === config.caseId && item.type === config.type);
        },

        pick(fileList) {
            this.$store.uploads.add(fileList, config);
            if (this.$refs.input) this.$refs.input.value = '';
        },

        cancel(item) { this.$store.uploads.cancel(item); },
        retry(item) { this.$store.uploads.retry(item); },
        remove(item) { this.$store.uploads.remove(item); },
        statusText(item) { return this.$store.uploads.statusText(item); },
        formatSize,
    };
}
