@extends('admin.layout')

@section('title', 'Yeni Yer İstekleri')

@section('content')
    <h1>Yeni Yer İstekleri</h1>

    <div class="filters">
        <a href="{{ route('admin.new-place-requests.index') }}">Tümü</a>
        <a href="{{ route('admin.new-place-requests.index', ['status' => 'pending']) }}">Bekleyen</a>
        <a href="{{ route('admin.new-place-requests.index', ['status' => 'approved']) }}">Onaylanan</a>
        <a href="{{ route('admin.new-place-requests.index', ['status' => 'rejected']) }}">Reddedilen</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Başlık</th>
                <th>Gönderen</th>
                <th>Açıklama</th>
                <th>Konum</th>
                <th>Tarih</th>
                <th>Durum</th>
                <th>Karar</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requests as $r)
                <tr>
                    <td>{{ $r->title }}</td>
                    <td>{{ $r->user->first_name }} {{ $r->user->last_name }}<br><small>{{ $r->user->email }}</small></td>
                    <td>{{ $r->description }}</td>
                    <td>{{ $r->lat }}, {{ $r->long }}</td>
                    <td>{{ $r->created_at->format('d.m.Y H:i') }}</td>
                    <td><span class="status status-{{ $r->status }}">{{ $r->status }}</span></td>
                    <td>
                        @if ($r->status === 'pending')
                            <form class="decide-form" method="POST" action="{{ route('admin.new-place-requests.decide', $r->id) }}">
                                @csrf
                                @method('PATCH')
                                <select name="district_id" required>
                                    <option value="">İlçe seç</option>
                                    @foreach ($districts as $district)
                                        <option value="{{ $district['id'] }}">{{ $district['title'] }}</option>
                                    @endforeach
                                </select>
                                <select name="category_id">
                                    <option value="">Kategori (öneriden al)</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category['id'] }}" @selected($r->category_id === $category['id'])>{{ $category['title'] }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" name="status" value="approved" class="approve">Onayla</button>
                                <button type="submit" name="status" value="rejected" class="reject" formnovalidate>Reddet</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Kayıt yok.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
