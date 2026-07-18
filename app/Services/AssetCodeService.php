<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class AssetCodeService
{
    public static function nextCode(?AssetCategory $category): string
    {
        $prefix = $category ? strtoupper($category->short_name) : 'AST';
        $year = date('Y');
        $count = Asset::whereYear('created_at', $year)->count() + 1;
        return $prefix . '-' . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    }

    public static function generateQrCode(Asset $asset): void
    {
        $url = route('asset.public.show', $asset->asset_code);

        $result = (new Builder(
            writer: new PngWriter,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
        ))->build();

        $path = 'qrcodes/' . $asset->asset_code . '.png';

        Storage::disk('public')->put($path, $result->getString());

        $asset->update(['qr_code_path' => $path]);
    }
}
