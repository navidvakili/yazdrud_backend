<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مشاهده گواهی - {{ $registration->fullname }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 900px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h1 {
            font-size: 18px;
            font-weight: 700;
        }
        .header .info {
            font-size: 12px;
            opacity: 0.9;
        }
        .body-content {
            padding: 0;
        }
        iframe {
            width: 100%;
            height: 80vh;
            border: none;
            display: block;
        }
        .footer {
            padding: 12px 24px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #6b7280;
        }
        .footer a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #4f46e5;
            color: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-download:hover {
            background: #4338ca;
        }
        .error-box {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        .error-box h2 {
            font-size: 20px;
            margin-bottom: 8px;
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>گواهی دوره آموزشی</h1>
                <div class="info">{{ $registration->fullname }} — کدملی: {{ $registration->kodmeli }} — {{ $registration->course->title ?? '' }} @if($registration->course->instructor) — مدرس: {{ $registration->course->instructor }} @endif</div>
            </div>
            <div>
                <a href="{{ $publicViewUrl }}?download=1" class="btn-download" target="_blank">
                    ⬇ دانلود
                </a>
            </div>
        </div>
        <div class="body-content">
            @if ($error)
                <div class="error-box">
                    <h2>خطا</h2>
                    <p>{{ $error }}</p>
                </div>
            @else
                <iframe src="{{ $publicViewUrl }}" title="پیش‌نمایش گواهی"></iframe>
            @endif
        </div>
        <div class="footer">
            <span>سیستم مدیریت آموزش</span>
            <a href="#">پرتال جامع</a>
        </div>
    </div>
</body>
</html>
