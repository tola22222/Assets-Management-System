<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use App\Services\ImageCompressor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Uploaded photos over 1 MB are stored compressed; smaller ones untouched. */
class ImageCompressorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** A noisy JPEG (noise barely compresses), well over 1 MB. */
    private function largeJpeg(int $width = 2400, int $height = 1200): string
    {
        $image = imagecreatetruecolor($width, $height);
        for ($i = 0; $i < 60000; $i++) {
            $x = mt_rand(0, $width);
            $y = mt_rand(0, $height);
            imagefilledrectangle($image, $x, $y, $x + mt_rand(2, 30), $y + mt_rand(2, 30), imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        ob_start();
        imagejpeg($image, null, 100);

        return ob_get_clean();
    }

    private function upload(string $bytes, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_a_large_photo_is_stored_at_one_megabyte_or_less(): void
    {
        $bytes = $this->largeJpeg();
        $this->assertGreaterThan(ImageCompressor::MAX_BYTES, strlen($bytes));

        $path = ImageCompressor::store($this->upload($bytes, 'phone.jpg'), 'assets');

        $this->assertStringEndsWith('.jpg', $path);
        $stored = Storage::disk('public')->get($path);
        $this->assertLessThanOrEqual(ImageCompressor::MAX_BYTES, strlen($stored));
        // Still a real picture, in the same shape.
        $size = getimagesizefromstring($stored);
        $this->assertSame(IMAGETYPE_JPEG, $size[2]);
        $this->assertEqualsWithDelta(2.0, $size[0] / $size[1], 0.02);
    }

    public function test_a_small_photo_is_stored_exactly_as_uploaded(): void
    {
        $file = UploadedFile::fake()->image('small.png', 300, 200);
        $bytes = file_get_contents($file->getRealPath());

        $path = ImageCompressor::store($file, 'assets');

        $this->assertStringEndsWith('.png', $path);
        $this->assertSame($bytes, Storage::disk('public')->get($path));
    }

    public function test_a_sideways_phone_photo_stays_upright(): void
    {
        // A landscape picture flagged "rotate 90° to view" (EXIF orientation
        // 6), the way a phone held upright saves it.
        $exif = "Exif\0\0"."II*\0"."\x08\0\0\0"."\x01\0"."\x12\x01"."\x03\0"."\x01\0\0\0"."\x06\0\0\0"."\0\0\0\0";
        $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
        $jpeg = $this->largeJpeg();
        $flagged = substr($jpeg, 0, 2).$app1.substr($jpeg, 2);

        $path = ImageCompressor::store($this->upload($flagged, 'portrait.jpg'), 'verifications');

        $size = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertGreaterThan($size[0], $size[1], 'the stored photo should be portrait');
    }

    public function test_an_upload_through_the_app_is_compressed(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);

        $this->actingAs($opm)->post('/api/suppliers', [
            'name' => 'ABC Shop',
            'image' => $this->upload($this->largeJpeg(), 'shop.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $path = Supplier::firstOrFail()->image_path;
        $this->assertLessThanOrEqual(ImageCompressor::MAX_BYTES, Storage::disk('public')->size($path));
    }
}
