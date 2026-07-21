<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e5e7eb;">
        @php($items = $payload['extraData']['items'] ?? [])
        <h2 style="color: #128a43; margin-top: 0;">{{ count($items) }} Assets Missing Required Fields</h2>
        <p>These assets have been on the register for {{ $payload['extraData']['graceDays'] ?? 7 }}+ days without a required or recommended field filled in.</p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px;">
            <tr style="text-align: left; border-bottom: 2px solid #e5e7eb;">
                <th style="padding: 8px 0;">Asset ID</th>
                <th style="padding: 8px 0;">Description</th>
                <th style="padding: 8px 0;">Missing</th>
            </tr>
            @foreach($items as $item)
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 8px 0;">{{ $item['assetId'] ?? 'N/A' }}</td>
                <td style="padding: 8px 0;">{{ $item['description'] ?? 'N/A' }}</td>
                <td style="padding: 8px 0;">{{ $item['missingFields'] ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </table>

        @if(!empty($payload['extraData']['link']))
        <p style="margin-bottom: 0;"><a href="{{ $payload['extraData']['link'] }}" style="color:#128a43;font-weight:bold;">View the filtered register &rarr;</a></p>
        @endif
    </div>
</body>
</html>
