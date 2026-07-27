@extends('admin.layout')

@section('title', 'Geri Bildirimler')

@section('content')
    <h1>Geri Bildirimler</h1>

    <div class="filters">
        <a href="{{ route('admin.feedback.index') }}">Tümü</a>
        <a href="{{ route('admin.feedback.index', ['kind' => 'complaint']) }}">Şikayet</a>
        <a href="{{ route('admin.feedback.index', ['kind' => 'request']) }}">Talep</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tür</th>
                <th>Gönderen</th>
                <th>Açıklama</th>
                <th>Konum</th>
                <th>Tarih</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($feedback as $f)
                <tr>
                    <td>{{ $f->kind }}</td>
                    <td>{{ $f->user->first_name }} {{ $f->user->last_name }}<br><small>{{ $f->user->email }}</small></td>
                    <td>{{ $f->description }}</td>
                    <td>
                        @if ($f->location_id)
                            location #{{ $f->location_id }}
                        @elseif ($f->lat !== null)
                            {{ $f->lat }}, {{ $f->long }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $f->created_at->format('d.m.Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Kayıt yok.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
