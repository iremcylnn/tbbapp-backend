<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Admin Panel') — TbbApp</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f5f5f5; color: #222; }
        nav { background: #1f2937; padding: 0.75rem 1.5rem; display: flex; gap: 1.25rem; align-items: center; }
        nav a { color: #e5e7eb; text-decoration: none; font-size: 0.95rem; }
        nav a:hover { color: #fff; text-decoration: underline; }
        nav form { margin-left: auto; }
        nav button { background: none; border: 1px solid #4b5563; color: #e5e7eb; padding: 0.3rem 0.75rem; border-radius: 4px; cursor: pointer; }
        main { padding: 1.5rem; max-width: 1100px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { text-align: left; padding: 0.6rem 0.8rem; border-bottom: 1px solid #e5e7eb; font-size: 0.9rem; vertical-align: top; }
        th { background: #f3f4f6; }
        .status { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.8rem; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .flash { background: #d1fae5; color: #065f46; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .errors { background: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
        select, button.action { padding: 0.3rem; font-size: 0.85rem; }
        button.approve { background: #10b981; color: #fff; border: none; border-radius: 4px; padding: 0.35rem 0.7rem; cursor: pointer; }
        button.reject { background: #ef4444; color: #fff; border: none; border-radius: 4px; padding: 0.35rem 0.7rem; cursor: pointer; }
        .decide-form { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; }
        pre { white-space: pre-wrap; word-break: break-word; margin: 0; font-size: 0.8rem; }
        .filters { margin-bottom: 1rem; }
        .filters a { margin-right: 0.75rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <nav>
        <a href="{{ route('admin.new-place-requests.index') }}">Yeni Yer İstekleri</a>
        <a href="{{ route('admin.feedback.index') }}">Geri Bildirimler</a>
        <a href="{{ route('admin.action-logs.index') }}">İşlem Kayıtları</a>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit">Çıkış</button>
        </form>
    </nav>
    <main>
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="errors">
                <ul style="margin:0; padding-left:1.2rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
