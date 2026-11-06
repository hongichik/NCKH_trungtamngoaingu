<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: #f4f7fb; color: #1f2d3d; }
        .box { max-width: 520px; width: 100%; background: #fff; border-radius: 16px; padding: 40px 32px; text-align: center; box-shadow: 0 10px 30px rgba(10, 74, 117, .08); }
        .code { font-size: 64px; font-weight: 800; line-height: 1; color: #0a4a75; letter-spacing: -2px; }
        h1 { font-size: 22px; margin: 16px 0 8px; }
        p { margin: 0 0 24px; color: #5b6b7b; line-height: 1.55; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        a.btn { display: inline-block; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; border: 1px solid #0a4a75; color: #0a4a75; }
        a.btn.primary { background: #0a4a75; color: #fff; }
    </style>
</head>
<body>
    <main class="box">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <a class="btn" href="javascript:history.back()">Quay lại</a>
            <a class="btn primary" href="{{ url('/') }}">Về trang chủ</a>
        </div>
    </main>
</body>
</html>
