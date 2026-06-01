<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 14px; color: #333; line-height: 1.6; }
        .container { max-width: 520px; margin: 32px auto; padding: 0 16px; }
        h2 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        ul { margin: 12px 0 20px; padding-left: 20px; }
        li { margin-bottom: 4px; }
        .footer { margin-top: 32px; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Hi {{ $displayName }},</h2>
        <p>This is a reminder that you have not logged any time in Jira on the following working days:</p>
        <ul>
            @foreach($missingDays as $day)
                <li>{{ \Carbon\Carbon::parse($day)->format('l, d M Y') }}</li>
            @endforeach
        </ul>
        <p>Please log your time in Jira at your earliest convenience.</p>
        <div class="footer">
            Sent by Worklog Tracker
        </div>
    </div>
</body>
</html>
