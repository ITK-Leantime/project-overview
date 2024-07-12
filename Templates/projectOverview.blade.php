@extends($layout)

@section('content')
@foreach($allTickets as $row)
    <div>
        {{$row["id"]}}
    </div>
@endforeach
@endsection
