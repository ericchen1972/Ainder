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

    const syncInput = () => {
        const transfer = new DataTransfer();
        selectedFiles.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        count.textContent = String(selectedFiles.length);
        submit.disabled = !(selectedFiles.length >= 2 && selectedFiles.length <= 6);
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

    const addFiles = (files) => {
        const images = files.filter((file) => ['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
        const combined = [...selectedFiles, ...images];
        clientError.textContent = combined.length > 6 ? '最多只能上傳 6 張照片。' : '';
        clientError.hidden = combined.length <= 6;
        selectedFiles = combined.slice(0, 6);
        syncInput();
        renderPhotoSlots();
    };

    toggle.addEventListener('click', () => {
        manualForm.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('manual-is-open');
        window.setTimeout(() => manualForm.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80);
    });
    input.addEventListener('change', () => addFiles([...input.files]));
    slots.forEach((slot, index) => slot.addEventListener('click', () => {
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
