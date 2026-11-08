@if (! empty($items))
    <nav aria-label="مسیر صفحه">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" title="داشبورد">
                    <i class="bi bi-house-door"></i>
                </a>
            </li>
            @foreach ($items as $item)
                @if (! empty($item['url']))
                    <li class="breadcrumb-item">
                        <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    </li>
                @else
                    <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] }}</li>
                @endif
            @endforeach
        </ol>
    </nav>
@endif
