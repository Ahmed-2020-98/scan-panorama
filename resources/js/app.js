import chunkUploader from './chunk-uploader';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('chunkUploader', chunkUploader);
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
