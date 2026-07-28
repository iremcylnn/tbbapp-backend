@extends('admin.layout')

@section('title', 'İşlem Kayıtları')

@section('content')
    <h1>İşlem Kayıtları</h1>

    <table>
        <thead>
            <tr>
                <th>İşlem</th>
                <th>Hedef</th>
                <th>IP</th>
                <th>Detay</th>
                <th>Tarih</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->target_type }} #{{ $log->target_id }}</td>
                    <td>{{ $log->ip_address }}</td>
                    <td><pre>{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td>
                    <td>{{ $log->created_at->format('d.m.Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Kayıt yok.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
