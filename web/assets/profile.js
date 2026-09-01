(() => {
    const OUTPUT_WIDTH = 720;
    const OUTPUT_HEIGHT = 1280;
    const WEBP_QUALITY = 0.84;
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

    const loadWithImageElement = (file) => new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve({
                width: image.naturalWidth,
                height: image.naturalHeight,
                draw: (context, ...args) => context.drawImage(image, ...args),
                close: () => {},
            });
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('IMAGE_DECODE_FAILED'));
        };
        image.src = url;
    });

    const decodePhoto = async (file) => {
        if (typeof createImageBitmap !== 'function') {
            return loadWithImageElement(file);
        }
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        return {
            width: bitmap.width,
            height: bitmap.height,
            draw: (context, ...args) => context.drawImage(bitmap, ...args),
            close: () => bitmap.close(),
        };
    };

    const canvasToWebp = (canvas) => new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => blob ? resolve(blob) : reject(new Error('WEBP_ENCODE_FAILED')),
            'image/webp',
            WEBP_QUALITY,
        );
    });

    const processedFilename = (name) => {
        const base = name.replace(/\.[^.]*$/, '').replace(/[^a-zA-Z0-9_-]+/g, '-');
        return `${base || 'ainder-photo'}.webp`;
    };

    const preprocessPhoto = async (file) => {
        const source = await decodePhoto(file);
        try {
            const scale = Math.max(OUTPUT_WIDTH / source.width, OUTPUT_HEIGHT / source.height);
            const cropWidth = OUTPUT_WIDTH / scale;
            const cropHeight = OUTPUT_HEIGHT / scale;
            const sourceX = Math.max(0, (source.width - cropWidth) / 2);
            const sourceY = Math.max(0, (source.height - cropHeight) / 2);
            const canvas = document.createElement('canvas');
            canvas.width = OUTPUT_WIDTH;
            canvas.height = OUTPUT_HEIGHT;
            const context = canvas.getContext('2d', { alpha: false });
            if (!context) throw new Error('CANVAS_UNAVAILABLE');
            source.draw(
                context,
                sourceX,
                sourceY,
                cropWidth,
                cropHeight,
                0,
                0,
                OUTPUT_WIDTH,
                OUTPUT_HEIGHT,
            );
            const blob = await canvasToWebp(canvas);
            return new File([blob], processedFilename(file.name), {
                type: 'image/webp',
                lastModified: Date.now(),
            });
        } finally {
            source.close();
        }
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
                selectedFiles.push(await preprocessPhoto(file));
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
