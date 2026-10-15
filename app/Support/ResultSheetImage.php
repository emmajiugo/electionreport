<?php

namespace App\Support;

use App\Enums\AppMode;
use App\Models\ResultSheet;
use GdImage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use RuntimeException;

/**
 * An uploaded result sheet photo, re-encoded so nothing from the original file survives: re-encoding
 * drops EXIF metadata, which on phone photos includes the GPS position of the voter who took it.
 */
final readonly class ResultSheetImage
{
    /**
     * A public file, not a bucket path: practice sheets all point at this one sample image.
     */
    public const string SAMPLE_PATH = 'images/sample-result-sheet.svg';

    private const string SAMPLE_HASH = '0000000000000000';

    private const int JPEG_QUALITY = 82;

    private const int MAX_KILOBYTES = 2048;

    private const int MAX_PIXELS_PER_SIDE = 2000;

    private function __construct(public string $jpeg, public string $hash, private bool $isSample = false) {}

    /**
     * In test mode the voter's photo never leaves their phone, so practice can't fill the bucket
     * before election day. The browser sends only `sample_result_sheet`, and a sample sheet stands
     * in for the photo so reviews, figures and standings can still be practised.
     */
    public static function fromRequest(Request $request): ?self
    {
        if (AppMode::current()->isTest()) {
            return $request->boolean('sample_result_sheet') ? new self('', self::SAMPLE_HASH, isSample: true) : null;
        }

        $file = $request->file('result_sheet');

        return $file instanceof UploadedFile ? self::fromUpload($file) : null;
    }

    public static function fromUpload(UploadedFile $file): self
    {
        $source = imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('The uploaded result sheet could not be read as an image.');
        }

        $image = self::onWhiteBackground($source);

        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);
        $jpeg = (string) ob_get_clean();

        return new self($jpeg, self::differenceHash($image));
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function rules(bool $required): array
    {
        if (AppMode::current()->isTest()) {
            return ['sample_result_sheet' => [$required ? 'accepted' : 'boolean']];
        }

        return [
            'result_sheet' => [$required ? 'required' : 'nullable', self::rule()],
            'sample_result_sheet' => ['prohibited'],
        ];
    }

    /**
     * The browser shrinks photos to about 1600px before upload; these limits leave headroom while
     * keeping a hostile upload from exhausting memory when it is decoded.
     */
    private static function rule(): File
    {
        return File::image()
            ->types(['jpg', 'jpeg', 'png', 'webp'])
            ->max(self::MAX_KILOBYTES)
            ->dimensions(Rule::dimensions()->maxWidth(self::MAX_PIXELS_PER_SIDE)->maxHeight(self::MAX_PIXELS_PER_SIDE));
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $attribute): array
    {
        return [
            "{$attribute}.image" => 'Upload a photo of the result sheet.',
            "{$attribute}.mimes" => 'Upload a photo of the result sheet.',
            "{$attribute}.max" => 'This photo is too large. Please try again.',
            "{$attribute}.dimensions" => 'This photo is too large. Please try again.',
        ];
    }

    /**
     * Saves the photo publicly and returns its path. The sample is already public, so it is never saved.
     */
    public function store(int $electionId): string
    {
        if ($this->isSample) {
            return self::SAMPLE_PATH;
        }

        $path = AppMode::current()->value."/result-sheets/{$electionId}/".Str::uuid().'.jpg';

        // Visibility comes from the disk, never per file: Laravel Cloud buckets (Cloudflare R2) reject per-object ACLs.
        // Every photo gets a fresh path and is never rewritten, so browsers and CDNs may keep it for good.
        Storage::disk(ResultSheet::disk())->put($path, $this->jpeg, [
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $path;
    }

    /**
     * JPEG has no transparency, so transparent PNG and WebP pixels would otherwise turn black.
     */
    private static function onWhiteBackground(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $image = imagecreatetruecolor($width, $height);

        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagecopy($image, $source, 0, 0, 0, 0, $width, $height);

        return $image;
    }

    /**
     * A 64-bit perceptual fingerprint as 16 hex characters. Unlike a file checksum it survives
     * resizing and recompression, so the same photo uploaded twice produces the same hash.
     */
    private static function differenceHash(GdImage $image): string
    {
        $small = imagecreatetruecolor(9, 8);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 9, 8, imagesx($image), imagesy($image));

        $bits = '';

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= self::brightness($small, $x, $y) > self::brightness($small, $x + 1, $y) ? '1' : '0';
            }
        }

        return implode('', array_map(
            fn (string $nibble): string => dechex((int) bindec($nibble)),
            str_split($bits, 4),
        ));
    }

    private static function brightness(GdImage $image, int $x, int $y): int
    {
        $rgb = imagecolorat($image, $x, $y);

        return (int) (0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
    }
}
