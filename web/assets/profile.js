import { preprocessProfilePhoto } from './client-photo-processor.js';

(() => {
    const toggle = document.querySelector('.manual-toggle');
    const manualForm = document.querySelector('#manual-form');
    const input = document.querySelector('#photos');
    const slots = [...document.querySelectorAll('.photo-slot')];
    const submit = document.querySelector('.register-submit');
    const count = document.querySelector('.photo-count');
    const clientError = document.querySelector('.photo-client-error');
    if (!toggle || !manualForm || !input || !submit || !count || !clientError) return;

    let selectedFiles = [];
    let objectUrls = [];
    let isProcessing = false;
    const defaultSubmitLabel = submit.textContent;

    const syncInput = () => {
        const transfer = new DataTransfer();
        selectedFiles.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        count.textContent = String(selectedFiles.length);
        submit.disabled = isProcessing
            || !(selectedFiles.length >= 2 && selectedFiles.length <= 6);
        submit.textContent = isProcessing ? '照片處理中…' : defaultSubmitLabel;
    };

    const renderPhotoSlots = () => {
        objectUrls.forEach((url) => URL.revokeObjectURL(url));
        objectUrls = [];
        slots.forEach((slot, index) => {
            slot.replaceChildren();
            const file = selectedFiles[index];
            if (!file) {
                const plus = document.createElement('span');
                plus.textContent = '＋';
                slot.append(plus);
                slot.setAttribute('aria-label', `新增第 ${index + 1} 張照片`);
                return;
            }
            const url = URL.createObjectURL(file);
            objectUrls.push(url);
            const image = document.createElement('img');
            image.src = url;
            image.alt = `第 ${index + 1} 張照片預覽`;
            const remove = document.createElement('span');
            remove.className = 'photo-remove';
            remove.textContent = '×';
            remove.setAttribute('aria-hidden', 'true');
            slot.append(image, remove);
            slot.setAttribute('aria-label', `移除第 ${index + 1} 張照片`);
        });
    };

    const addFiles = async (files) => {
        const images = files.filter((file) => ['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
        const available = Math.max(0, 6 - selectedFiles.length);
        clientError.textContent = images.length > available ? '最多只能上傳 6 張照片。' : '';
        clientError.hidden = images.length <= available;
        isProcessing = true;
        syncInput();
        try {
            for (const file of images.slice(0, available)) {
                selectedFiles.push(await preprocessProfilePhoto(file));
                renderPhotoSlots();
            }
        } catch (error) {
            clientError.textContent = '照片無法處理，請改用其他 JPG、PNG 或 WebP 圖片。';
            clientError.hidden = false;
        } finally {
            isProcessing = false;
            syncInput();
            renderPhotoSlots();
        }
    };

    toggle.addEventListener('click', () => {
        manualForm.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('manual-is-open');
        window.setTimeout(() => manualForm.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80);
    });
    input.addEventListener('change', () => {
        const files = [...input.files];
        input.value = '';
        void addFiles(files);
    });
    slots.forEach((slot, index) => slot.addEventListener('click', () => {
        if (isProcessing) return;
        if (selectedFiles[index]) {
            selectedFiles.splice(index, 1);
            syncInput();
            renderPhotoSlots();
        } else {
            input.click();
        }
    }));

    renderPhotoSlots();
    syncInput();
})();
