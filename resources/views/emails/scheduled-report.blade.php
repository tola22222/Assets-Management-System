<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e5e7eb;">
        <h2 style="color: #128a43; margin-top: 0;">Asset Count &amp; Reconciliation Due</h2>
        <p>The {{ $periodLabel }} asset counting cycle is due, per the Asset Checking &amp; Counting Manual Guideline. Please schedule the physical count and reconciliation with the Operations &amp; HR team.</p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Total assets on register</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $summary['total_assets'] }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Active assets</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $summary['active_assets'] }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Disposed assets</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $summary['disposed_assets'] }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Reported lost</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $summary['lost_assets'] }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Reported broken</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $summary['broken_assets'] }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Pending disposal requests</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $summary['pending_disposals'] }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Pending transfers</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $summary['pending_transfers'] }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">Pending returns</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ $summary['pending_returns'] }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;">Verifications recorded since last cycle</td>
                <td style="padding: 8px 0; text-align: right; font-weight: bold;">{{ $summary['verifications_since_last'] }}</td>
            </tr>
        </table>

        <p style="margin-bottom: 0;">Log in to review the full inventory and record the physical count.</p>
    </div>
</body>
</html>
