<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e5e7eb;">
        <h2 style="color: #128a43; margin-top: 0;">Asset Flagged: {{ $payload['extraData']['status'] ?? 'Issue Reported' }}</h2>
        <p>{{ $payload['flaggedBy'] ?? 'A staff member' }} flagged an asset {{ !empty($payload['location']) ? 'at ' . $payload['location'] : '' }}.</p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Asset ID</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $payload['assetId'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Description</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $payload['description'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Location</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $payload['location'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Flagged by</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $payload['flaggedBy'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;">Date/time</td>
                <td style="padding: 8px 0; text-align: right; font-weight: bold;">{{ $payload['extraData']['flaggedAt'] ?? now()->format('d M Y, H:i') }}</td>
            </tr>
        </table>

        @if(!empty($payload['note']))
        <p style="background:#f9fafb;border-left:3px solid #128a43;padding:10px 14px;margin:0 0 20px;">{{ $payload['note'] }}</p>
        @endif

        @if(!empty($payload['extraData']['link']))
        <p style="margin-bottom: 0;"><a href="{{ $payload['extraData']['link'] }}" style="color:#128a43;font-weight:bold;">View this asset &rarr;</a></p>
        @endif
    </div>
</body>
</html>
