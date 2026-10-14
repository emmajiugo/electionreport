const MAX_SIDE = 1600;
const JPEG_QUALITY = 0.8;

/**
 * Phone photos are several megabytes; shrinking them before upload keeps the upload quick on a
 * weak connection. Drawing onto a canvas also applies the EXIF rotation and drops the metadata.
 */
export async function shrinkPhoto(file: File): Promise<File> {
    const bitmap = await createImageBitmap(file, {
        imageOrientation: 'from-image',
    });
    const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);

    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Canvas is not supported.');
    }

    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY),
    );

    if (!blob) {
        throw new Error('The photo could not be encoded.');
    }

    return new File([blob], 'result-sheet.jpg', { type: 'image/jpeg' });
}
