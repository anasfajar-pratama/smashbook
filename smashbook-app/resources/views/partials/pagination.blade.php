@if ($paginator->hasPages())
  <div class="pager">
    <div>
      Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data
    </div>
    <div class="links">
      @if ($paginator->onFirstPage())
        <span class="disabled">‹</span>
      @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>
      @endif

      @foreach ($elements as $element)
        @if (is_string($element))
          <span class="disabled">{{ $element }}</span>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <span class="current">{{ $page }}</span>
            @else
              <a href="{{ $url }}">{{ $page }}</a>
            @endif
          @endforeach
        @endif
      @endforeach

      @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>
      @else
        <span class="disabled">›</span>
      @endif
    </div>
  </div>
@endif
