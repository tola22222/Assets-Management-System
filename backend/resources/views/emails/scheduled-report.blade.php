@php
    // Same look as the attached Inventory List: green PEPY logo + Khmer name,
    // blue title, red headers, orange section rows, dotted lines, green total.
    // Inline styles and tables only — email clients ignore <style> blocks.
    $logoPath = resource_path('images/pepy-logo-green.png');
    $logoSrc = isset($message) && is_file($logoPath) ? $message->embed($logoPath) : null;

    $sections = [
        'Asset register' => [
            ['Total assets on register', $summary['total_assets'], false],
            ['Active assets', $summary['active_assets'], false],
            ['Disposed assets', $summary['disposed_assets'], false],
        ],
        'Needs attention' => [
            ['Reported lost', $summary['lost_assets'], true],
            ['Reported broken', $summary['broken_assets'], true],
        ],
        'Workflows in progress' => [
            ['Pending disposal requests', $summary['pending_disposals'], true],
            ['Pending transfers', $summary['pending_transfers'], true],
            ['Pending returns', $summary['pending_returns'], true],
            ['Verifications recorded since last cycle', $summary['verifications_since_last'], false],
        ],
    ];

    $cell = 'padding:8px 12px;border-left:1px solid #BFBFBF;border-right:1px solid #BFBFBF;border-bottom:1px dotted #BFBFBF;font-size:13px;';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:24px 12px;background:#F3F4F6;font-family:Calibri,Arial,sans-serif;color:#1F2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
<tr><td align="center">
    <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;border-collapse:collapse;background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;">

        {{-- Letterhead: logo + organisation, then the report title. --}}
        <tr>
            <td style="padding:24px 28px 8px 28px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                    <tr>
                        @if ($logoSrc)
                            <td width="150" valign="middle" style="width:150px;">
                                <img src="{{ $logoSrc }}" alt="PEPY Empowering Youth" height="56" style="display:block;height:56px;width:auto;border:0;">
                            </td>
                        @endif
                        <td valign="middle" align="center" style="font-family:'Khmer OS Muol Light','Kantumruy Pro',Arial,sans-serif;font-size:18px;font-weight:bold;color:#0E7A3B;">
                            អង្គការ លើកកម្ពស់យុវជន
                        </td>
                        {{-- Same width as the logo cell, so the name sits in the true centre. --}}
                        @if ($logoSrc)
                            <td width="150" style="width:150px;">&nbsp;</td>
                        @endif
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:4px 28px 0 28px;font-family:Cambria,Georgia,serif;font-size:22px;color:#1F5FAD;">
                Periodic Asset Summary Report
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:4px 28px 18px 28px;font-family:Cambria,Georgia,serif;font-size:15px;font-weight:bold;text-decoration:underline;color:#111827;">
                {{ $periodLabel }} · PEPY Office / Learning Center / Target High Schools
            </td>
        </tr>

        <tr>
            <td style="padding:0 28px 16px 28px;font-size:14px;line-height:1.55;color:#374151;">
                Here is the {{ $periodLabel }} snapshot of the asset register and its in-flight workflows. This is an informational summary, separate from the Asset Checking &amp; Counting Manual's Feb/Aug count reminder — check the register below for anything that needs follow-up.
            </td>
        </tr>

        {{-- The numbers, in the Inventory List table style. --}}
        <tr>
            <td style="padding:0 28px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                    <tr>
                        <td style="padding:9px 12px;border:1px solid #BFBFBF;border-top:1px solid #808080;border-bottom:1px solid #808080;font-size:13px;font-weight:bold;color:#C00000;">Item</td>
                        <td width="110" align="center" style="width:110px;padding:9px 12px;border:1px solid #BFBFBF;border-top:1px solid #808080;border-bottom:1px solid #808080;font-size:13px;font-weight:bold;color:#C00000;">Count</td>
                    </tr>
                    @foreach ($sections as $title => $rows)
                        <tr>
                            <td colspan="2" style="padding:7px 12px;background:#FFC000;border:1px solid #BFBFBF;font-size:13px;font-weight:bold;color:#1F2937;">{{ $title }}</td>
                        </tr>
                        @foreach ($rows as [$label, $value, $flag])
                            <tr>
                                <td style="{{ $cell }}">{{ $label }}</td>
                                <td align="center" style="{{ $cell }}font-weight:bold;{{ $flag && $value > 0 ? 'color:#C00000;' : 'color:#111827;' }}">{{ $value }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr>
                        <td style="padding:9px 12px;background:#E2EFDA;border:1px solid #BFBFBF;font-size:13px;font-weight:bold;">Total assets on register</td>
                        <td align="center" style="padding:9px 12px;background:#00B050;border:1px solid #BFBFBF;font-size:14px;font-weight:bold;color:#FFFFFF;">{{ $summary['total_assets'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>

        @if (! empty($hasAttachment))
            <tr>
                <td style="padding:18px 28px 0 28px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                        <tr>
                            <td style="padding:12px 14px;background:#ECF7F0;border:1px solid #B7E1C7;border-radius:8px;font-size:13px;line-height:1.5;color:#0E5C2E;">
                                <strong>Attached:</strong> the full asset register as an Excel file (Inventory List) — grouped by category, with totals and filter dropdowns.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        <tr>
            <td align="center" style="padding:22px 28px 6px 28px;">
                <a href="{{ url('/app/reports') }}" style="display:inline-block;padding:11px 22px;background:#128A43;color:#FFFFFF;text-decoration:none;font-size:14px;font-weight:bold;border-radius:8px;">Open PEPY Assets</a>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:6px 28px 24px 28px;font-size:13px;color:#6B7280;">
                Log in to review the full inventory and any pending workflow items.
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:12px 28px;border-top:1px solid #E5E7EB;font-size:11px;color:#9CA3AF;">
                Sent automatically by PEPY Assets · {{ now()->format('j M Y') }}
            </td>
        </tr>
    </table>
</td></tr>
</table>
</body>
</html>
