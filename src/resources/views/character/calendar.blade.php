@extends('web::character.layouts.view', ['viewname' => 'calendar', 'breadcrumb' => trans('web::seat.calendar')])

@section('page_header', trans_choice('web::seat.character', 1) . ' ' . trans('web::seat.calendar'))

@inject('request', 'Illuminate\Http\Request')

@section('character_content')

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">
        {{ trans('web::seat.calendar_events') }}
      </h3>
      <div class="card-tools">
        <div class="input-group input-group-sm">
          <a href="{{ route('tools.jobs.dispatch', ['character_id' => $character->character_id, 'job_name' => 'character.calendar']) }}"
             class="btn btn-sm btn-light">
            <i class="fas fa-sync" data-toggle="tooltip" title="{{ trans('web::seat.update_calendar') }}"></i>
          </a>
        </div>
      </div>
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