<!doctype html>
<html lang="ja">
<head><meta charset="utf-8"><title>ご予約の確認 | FORM</title></head>
<body style="margin:0;padding:32px 16px;background:#f7f5f1;font-family:Arial,'Hiragino Kaku Gothic ProN',sans-serif;color:#292b29;line-height:1.8">
<div style="max-width:560px;margin:auto;background:#fff;padding:36px;border-radius:16px">
    <p style="font-size:24px;letter-spacing:5px;font-weight:bold;margin-top:0">FORM<span style="color:#e98169">.</span></p>
    <h1 style="font-size:22px">ご予約を承りました</h1>
    <p>{{ $details['client']['name'] }} 様</p>
    <p>FORMをご予約いただきありがとうございます。<br>当日お会いできることを楽しみにしています。</p>
    <table style="width:100%;border-collapse:collapse;text-align:left">
        <tr><th style="padding:12px 0;border-bottom:1px solid #eee">予約番号</th><td style="padding:12px;border-bottom:1px solid #eee">{{ $details['reference'] }}</td></tr>
        <tr><th style="padding:12px 0;border-bottom:1px solid #eee">日時</th><td style="padding:12px;border-bottom:1px solid #eee">{{ $details['date'] }} {{ $details['time'] }}〜{{ $details['end_time'] }}（日本時間）</td></tr>
        <tr><th style="padding:12px 0;border-bottom:1px solid #eee">トレーナー</th><td style="padding:12px;border-bottom:1px solid #eee">{{ $details['trainer']['name'] }}</td></tr>
        <tr><th style="padding:12px 0;border-bottom:1px solid #eee">メニュー</th><td style="padding:12px;border-bottom:1px solid #eee">{{ config('booking.name') }} {{ $details['duration'] }}分</td></tr>
        <tr><th style="padding:12px 0;border-bottom:1px solid #eee">料金</th><td style="padding:12px;border-bottom:1px solid #eee">¥{{ number_format($details['price']) }}（税込）</td></tr>
    </table>
    @if($details['notes'])<p><strong>ご要望</strong><br>{{ $details['notes'] }}</p>@endif
    <p style="margin:28px 0"><a href="{{ route('reservations.show', $reservation) }}" style="display:inline-block;padding:12px 24px;border-radius:8px;background:#292b29;color:white;text-decoration:none">予約内容を確認する</a></p>
    <p style="font-size:13px;color:#777">お支払いは当日店頭にて承ります。動きやすい服装でお越しください。<br>キャンセルはマイページからお手続きいただけます。</p>
    <p style="font-size:11px;color:#999;margin-bottom:0">FORM PERSONAL TRAINING<br>このメールは予約システムから自動送信されています。</p>
</div>
</body>
</html>
