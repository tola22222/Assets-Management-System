<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print QR - {{ $asset->asset_code }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/Favicon1080x1080.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/Favicon1080x1080.png') }}">
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .qr-label {
            text-align: center;
            border: 2px dashed #ccc;
            padding: 20px;
            display: inline-block;
        }
        img {
            width: 200px;
            height: 200px;
        }
        .asset-code {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
            font-family: monospace;
        }
        .asset-name {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        @media print {
            body { padding: 0; }
            .qr-label { border: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="qr-label">
        <img src="{{ $qrUrl }}" alt="QR Code">
        <div class="asset-code">{{ $asset->asset_code }}</div>
        <div class="asset-name">{{ $asset->name }}</div>
    </div>
</body>
</html>
