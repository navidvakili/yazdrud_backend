<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>گواهی دوره آموزشی</title>
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'btitr', sans-serif;
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }

        .certificate-wrapper {
            width: 100%;
            height: 100%;
            padding: 190px 300px 20px 0px;
            box-sizing: border-box;
            background-image: url('{{ public_path('images/certificate.jpg') }}');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 100% 100%;
            position: relative;
        }

        .certificate-body {
            text-align: center;
            padding-top: 50px;
        }

        .body-text {
            font-size: 18px;
            line-height: 2;
            color: #222;
            margin: 0 auto 20px;
            text-align: center;
        }

        .body-text .highlight-name {
            font-size: 38px;
            font-weight: bold;
            color: #1a3a6b;
        }

        .body-text .highlight-course {
            font-size: 38px;
            font-weight: bold;
            line-height: 20px;
            color: #1a3a6b;
        }

        .body-text .highlight-detail {
            font-weight: bold;
            color: #1a3a6b;
        }

        .issued-date {
            font-size: 12px;
            color: #444;
            margin-top: 15px;
        }

        .bottom-row {
            position: absolute;
            bottom: 100px;
            left: 50px;
            width:60%;
        }

        .bottom-row table {
            width: 100%;
        }

        .bottom-row td {
            vertical-align: bottom;
            text-align: center;
        }

        .qrcode-area img {
            width: 55px;
            height: 55px;
        }

        .qrcode-area .qrcode-label {
            font-size: 4px;
            color: #888;
            margin-top: 3px;
        }

        .serial-code {
            font-size: 9px;
            color: #777;
            direction: ltr;
        }

        .signature-area {
            text-align: center;
            position: relative;
        }

        .signature-area .sig-line {
            width: 180px;
            height: 1px;
            border-top: 1px solid #333;
            margin: 30px auto 5px;
        }

        .signature-area .sig-title {
            font-size: 10px;
            color: #555;
            font-weight: bold;
        }

        .signature-area .sig-name {
            font-size: 10px;
            color: #1a3a6b;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="certificate-wrapper">
        {{-- محتوای اصلی --}}
        <div class="certificate-body">
            <div class="body-text">
                بدین وسیله گواهی می شود<br>
                <span class="highlight-name">{{ $fullname }}</span><br>
                در کارگاه آموزشی<br>
                <span class="highlight-course">{{ $course_title }}</span><br>
                که <span class="highlight-detail">{{ $course_month_year }}</span> توسط مرکز کارآفرینی و نوآوری دانشگاه علم و هنر به مدت <span class="highlight-detail">{{ $course_duration }} ساعت</span> برگزار گردید، شرکت نموده اند.
            </div>
        </div>
    </div>
    {{-- ردیف پایین: امضا - رمزینه - شماره سریال --}}
    <div class="bottom-row">
        <table style="width:100%">
            <tr>
                <td style="width: 34%; text-align:center;">
                    <div class="qrcode-area">
                        {!! preg_replace('/<\?xml.*?\?>/', '', $qrCode) !!}
                        <div class="qrcode-label">رمزینه اصالت گواهی</div>
                    </div>
                </td>
                <td style="width: 33%;"></td>
                <td style="width: 33%; text-align:center;">
                    <div class="signature-area">
                        <div class="sig-line"></div>
                        <div class="sig-name">دکتر جواد آقاجانی</div>
                        <div class="sig-title">معاون پژوهشی دانشگاه</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    <div style="margin-top: -18px; margin-bottom: 5px; position:absolute; z-index:99px; bottom:85px;left: 100px;"><img src="{{ public_path('images/signature.png') }}" width="165" style="opacity:0.75;" alt="امضا" /></div>
</body>
</html>
