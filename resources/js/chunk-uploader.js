/**
 * Alpine component that uploads files to the server in sequential chunks,
 * so large DICOM archives are not limited by PHP's upload_max_filesize.
 */
const MAX_ATTEMPTS = 3;

const uuid = () =>
    window.crypto?.randomUUID?.() ??
    `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

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

export default function chunkUploader({ url, type, chunkSize, maxBytes, extensions }) {
    return {
        items: [],
        dragging: false,
        busy: false,

        pick(fileList) {
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
                };

                if (!extensions.includes(extension)) {
                    item.status = 'error';
                    item.error = `امتداد غير مسموح. المسموح: ${extensions.join('، ')}`;
                } else if (file.size === 0) {
                    item.status = 'error';
                    item.error = 'الملف فارغ.';
                } else if (file.size > maxBytes) {
                    item.status = 'error';
                    item.error = `حجم الملف أكبر من الحد المسموح (${this.formatSize(maxBytes)}).`;
                }

                this.items.push(item);
            }

            if (this.$refs.input) this.$refs.input.value = '';

            this.run();
        },

        async run() {
            if (this.busy) return;

            this.busy = true;
            let uploaded = false;

            for (const item of this.items) {
                if (item.status === 'queued' && (await this.upload(item))) {
                    uploaded = true;
                }
            }

            this.busy = false;

            if (uploaded) {
                this.$wire.$refresh();
                setTimeout(() => {
                    this.items = this.items.filter((item) => item.status !== 'done');
                }, 3000);
            }
        },

        async upload(item) {
            item.status = 'uploading';
            item.controller = new AbortController();

            const total = Math.max(1, Math.ceil(item.size / chunkSize));
            const uploadId = item.uploadId ??= uuid();

            for (let index = 0; index < total; index++) {
                const form = new FormData();
                form.append('type', type);
                form.append('upload_id', uploadId);
                form.append('chunk_index', index);
                form.append('total_chunks', total);
                form.append('file_name', item.name);
                form.append('file_size', item.size);
                form.append('chunk', item.file.slice(index * chunkSize, Math.min(item.size, (index + 1) * chunkSize)), 'chunk');

                let response = null;

                for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
                    try {
                        response = await fetch(url, {
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
            return true;
        },

        cancel(item) {
            if (item.status === 'queued') {
                item.status = 'cancelled';
                item.error = 'تم إلغاء الرفع.';
            }
            item.controller?.abort();
        },

        retry(item) { item.error = null; item.status = 'queued'; item.progress = 0; this.run(); },

        remove(item) {
            this.items = this.items.filter((candidate) => candidate !== item);
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

        formatSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unit = 0;
            while (size >= 1024 && unit < units.length - 1) {
                size /= 1024;
                unit++;
            }
            return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
        },
    };
}
