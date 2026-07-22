<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e5e7eb;">
        <h2 style="color: #128a43; margin-top: 0;">{{ str_replace('_', ' ', $eventType) }}</h2>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            @if (!empty($payload['assetId']))
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Asset ID</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $payload['assetId'] }}</td>
                </tr>
            @endif
            @if (!empty($payload['description']))
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Description</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">{{ $payload['description'] }}</td>
                </tr>
            @endif
            @if (!empty($payload['category']))
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Category</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">{{ $payload['category'] }}</td>
                </tr>
            @endif
            @if (!empty($payload['location']))
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Location</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">{{ $payload['location'] }}</td>
                </tr>
            @endif
            @if (!empty($payload['flaggedBy']))
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">By</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">{{ is_object($payload['flaggedBy']) ? $payload['flaggedBy']->name : $payload['flaggedBy'] }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding: 8px 0;">Date/time</td>
                <td style="padding: 8px 0; text-align: right;">{{ now()->toDayDateTimeString() }}</td>
            </tr>
        </table>

        @if (!empty($payload['note']))
            <p><strong>Note:</strong> {{ $payload['note'] }}</p>
        @endif

        @if (!empty($payload['url']))
            <p style="margin-bottom: 0;"><a href="{{ $payload['url'] }}" style="color: #128a43;">View record</a></p>
        @endif
    </div>
</body>
</html>
