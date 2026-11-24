@extends('backend.layouts.app')

@section('title') {{ __($module_action) }} {{ __($module_title) }} @endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <x-backend.section-header>
                <x-slot name="toolbar">
                    @hasPermission('add_'.$module_name)
                        <a href="{{ route('backend.' . $module_name . '.create') }}" class="btn btn-primary">
                            <i class="ph ph-plus-circle"></i> {{ __('messages.new') }}
                        </a>
                    @endhasPermission
                </x-slot>
            </x-backend.section-header>

            <table id="datatable" class="table table-responsive"></table>
        </div>
    </div>
@endsection

@push('after-styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
@endpush

@push('after-scripts')
    <script src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
    <script defer>
        const columns = [
            {
                name: 'check',
                data: 'check',
                title: '<input type="checkbox" class="form-check-input" name="select_all_table" id="select-all-table" onclick="selectAllTable(this)">',
                orderable: false,
                searchable: false,
                exportable: false,
            },
            { data: 'name', name: 'name', title: @json(__('service.lbl_name')) },
            { data: 'status', name: 'status', title: @json(__('service.lbl_status')), orderable: false },
            { data: 'updated_at', name: 'updated_at', title: @json(__('service.lbl_update_at')), visible: false },
            { data: 'action', name: 'action', title: @json(__('service.lbl_action')), orderable: false, searchable: false },
        ];

        document.addEventListener('DOMContentLoaded', () => {
            initDatatable({
                url: @json(route('backend.worlds.index_data')),
                finalColumns: columns,
                orderColumn: [[3, 'desc']],
            });
        });
    </script>
@endpush
