@extends('web::character.layouts.view', ['viewname' => 'standings', 'breadcrumb' => trans_choice('web::seat.standings', 0)])

@section('page_header', trans_choice('web::seat.character', 1) . ' ' . trans_choice('web::seat.standings', 0))

@section('character_content')

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">{{ trans_choice('web::seat.standings', 0) }}</h3>
    </div>
    <div class="card-body">
      <div class="mb-3">
        <select multiple="multiple" id="dt-character-selector" class="form-control" style="width: 100%;">
          @foreach($character->refresh_token->user->characters as $character_info)
            @if($character_info->character_id == $character->character_id)
              <option selected="selected" value="{{ $character_info->character_id }}">{{ $character_info->name }}</option>
            @else
              <option value="{{ $character_info->character_id }}">{{ $character_info->name }}</option>
            @endif
          @endforeach
        </select>
      </div>

      {{ $dataTable->table() }}
    </div>
  </div>

@stop

@push('javascript')
  {!! $dataTable->scripts() !!}

  <script>
      $(document).ready(function() {
          $('#dt-character-selector')
              .select2()
              .on('change', function () {
                  window.LaravelDataTables['dataTableBuilder'].ajax.reload();
              });
      });
  </script>
@endpush