define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'mortgage/index',
                    add_url: 'mortgage/add',
                    edit_url: 'mortgage/edit',
                    del_url: 'mortgage/del',
                    multi_url: 'mortgage/multi',
                    table: 'mortgage',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'car_imgeas', title: __('Car_imgeas')},
                        {field: 'lending_date', title: __('Lending_date'), operate:'RANGE', addclass:'datetimerange'},
                        {field: 'bank_card', title: __('Bank_card')},
                        {field: 'invoice_monney', title: __('Invoice_monney'), operate:'BETWEEN'},
                        {field: 'registration_code', title: __('Registration_code')},
                        {field: 'tax', title: __('Tax'), operate:'BETWEEN'},
                        {field: 'business_risks', title: __('Business_risks'), operate:'BETWEEN'},
                        {field: 'insurance', title: __('Insurance'), operate:'BETWEEN'},
                        {field: 'firm_stage', title: __('Firm_stage')},
                        {field: 'mortgage_type', title: __('Mortgage_type'), searchList: {"new_car":__('Mortgage_type new_car'),"used_car":__('Mortgage_type used_car'),"yueda_car":__('Mortgage_type yueda_car'),"other_car":__('Mortgage_type other_car'),"south_firm":__('Mortgage_type south_firm'),"full_car":__('Mortgage_type full_car'),"full_other":__('Mortgage_type full_other')}, formatter: Table.api.formatter.normal},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});