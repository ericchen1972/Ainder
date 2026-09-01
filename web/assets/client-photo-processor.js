const OUTPUT_WIDTH = 720;
const OUTPUT_HEIGHT = 1280;
const WEBP_QUALITY = 0.84;

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

export async function preprocessProfilePhoto(file) {
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
}
