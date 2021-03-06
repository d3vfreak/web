@extends('web::character.wallet.layouts.view', ['sub_viewname' => 'transactions', 'breadcrumb' => trans('web::seat.wallet_transactions')])

@section('page_header', trans_choice('web::seat.character', 1) . ' ' . trans('web::seat.wallet_transactions'))

@inject('request', 'Illuminate\Http\Request')

@section('wallet_content')

  <div class="row">
    <div class="col-md-12">

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">
            {{ trans('web::seat.wallet_transactions') }}
          </h3>
          <div class="card-tools">
            <div class="input-group input-group-sm">
              <a href="{{ route('tools.jobs.dispatch', ['character_id' => $character->character_id, 'job_name' => 'character.wallet']) }}"
                 class="btn btn-sm btn-light">
                <i class="fas fa-sync" data-toggle="tooltip" title="{{ trans('web::seat.update_wallet') }}"></i>
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

    </div>
  </div>

@stop

@push('javascript')
  {!! $dataTable->scripts() !!}

  @include('web::includes.javascript.id-to-name')

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
