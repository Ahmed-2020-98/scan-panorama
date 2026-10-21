import chunkUploader, { uploadStore } from './chunk-uploader';

document.addEventListener('alpine:init', () => {
    window.Alpine.store('uploads', uploadStore);
    window.Alpine.data('chunkUploader', chunkUploader);
    window.Alpine.data('campaignSender', campaignSender);
});

/**
 * Campaign sending page: opens each chat in the WhatsApp app or in one reused
 * WhatsApp Web tab, and copies the campaign picture for pasting.
 */
function campaignSender() {
    const coarse = window.matchMedia?.('(pointer: coarse)').matches;
    let saved = null;
    try {
        saved = localStorage.getItem('campaign-mode');
    } catch {}

    return {
        mode: saved ?? (coarse ? 'app' : 'web'),
        copied: false,

        init() {
            this.$watch('mode', (value) => {
                try {
                    localStorage.setItem('campaign-mode', value);
                } catch {}
            });
        },

        get target() {
            return this.mode === 'app' ? '_self' : 'whatsapp-web';
        },

        link(phone, text) {
            const query = `phone=${phone}&text=${encodeURIComponent(text)}`;
            if (this.mode === 'app') return `whatsapp://send?${query}`;
            return coarse ? `https://wa.me/${phone}?text=${encodeURIComponent(text)}` : `https://web.whatsapp.com/send?${query}`;
        },

        async copyImage(url) {
            try {
                const blob = await (await fetch(url)).blob();
                const png = blob.type === 'image/png' ? blob : await toPng(blob);
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': png })]);
                this.copied = true;
                setTimeout(() => (this.copied = false), 2500);
            } catch {
                window.open(url, '_blank');
            }
        },
    };
}

/** Browsers only put PNG images on the clipboard. */
async function toPng(blob) {
    const bitmap = await createImageBitmap(blob);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0);
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
}

// Closing the tab or a full page reload would stop uploads in progress.
window.addEventListener('beforeunload', (event) => {
    if (window.Alpine?.store('uploads')?.active.length) {
        event.preventDefault();
        event.returnValue = '';
    }
});

/**
 * Copy text with a fallback for non-secure (http) contexts.
 */
window.copyText = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        const input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        const copied = document.execCommand('copy');
        input.remove();
        return copied;
    }
};
