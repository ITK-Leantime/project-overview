@extends($layout)

@section('content')
<h1>{{ __('projectoverview.dashboard_title') }}</h1>
@foreach($allTickets as $row)
    <div>
        {{ $row["id"] }}
    </div>
@endforeach
@endsection
