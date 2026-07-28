<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e5e7eb;">
        <h2 style="color: #128a43; margin-top: 0;">Asset Count &amp; Reconciliation Scheduled</h2>
        <p>The physical asset count is scheduled for <strong>{{ $payload['extraData']['date'] ?? 'soon' }}</strong>, per the Asset Checking &amp; Counting Manual Guideline (February &amp; August).</p>

        @if(!empty($payload['extraData']['locations']))
        <p style="margin: 0 0 6px; font-weight: bold;">Locations to cover:</p>
        <ul style="margin: 0 0 20px; padding-left: 20px;">
            @foreach($payload['extraData']['locations'] as $location)
            <li>{{ $location }}</li>
            @endforeach
        </ul>
        @endif

        <p>Match each item to the register, tag any untagged assets found, and report discrepancies (missing, relocated, or new items) to Operations &amp; HR.</p>

        @if(!empty($payload['extraData']['link']))
        <p style="margin-bottom: 0;"><a href="{{ $payload['extraData']['link'] }}" style="color:#128a43;font-weight:bold;">Open the printable/exportable register &rarr;</a></p>
        @endif
    </div>
</body>
</html>
