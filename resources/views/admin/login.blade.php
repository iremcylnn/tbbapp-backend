<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Admin Girişi — TbbApp</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        form { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.15); width: 280px; }
        h1 { font-size: 1.1rem; margin-top: 0; }
        input { width: 100%; padding: 0.5rem; box-sizing: border-box; margin-bottom: 0.75rem; border: 1px solid #d1d5db; border-radius: 4px; }
        button { width: 100%; padding: 0.5rem; background: #1f2937; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .errors { color: #991b1b; font-size: 0.85rem; margin-bottom: 0.75rem; }
    </style>
</head>
<body>
    <form method="POST" action="{{ route('admin.login.store') }}">
        @csrf
        <h1>Admin Girişi</h1>
        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        <input type="password" name="key" placeholder="Admin anahtarı" autofocus>
        <button type="submit">Giriş yap</button>
    </form>
</body>
</html>
