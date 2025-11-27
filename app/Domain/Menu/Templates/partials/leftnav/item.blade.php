@if(!isset($menuItem['role']) || $login::userIsAtLeast($menuItem['role'] ?? 'editor'))

    <li
        @if(
            $module == $menuItem['module']
            && (!isset($menuItem['active']) || in_array($action, $menuItem['active']))
        )
            class='active'
        @endif
    >
        <a href="{{ str_starts_with($menuItem['href'], 'http') ? $menuItem['href'] : BASE_URL . $menuItem['href'] }}"
           data-tippy-content="{{ strip_tags(__($menuItem['tooltip'])) }}"
           data-tippy-placement="right"
           preload="mouseover"
           @if(isset($menuItem['attributes']))
               @foreach($menuItem['attributes'] as $key => $value)
                   {{ $key }}="{{ $value }}"
               @endforeach
           @endif
        >
            {!! $menuItem['title'] !!}
        </a>
    </li>

@endif
