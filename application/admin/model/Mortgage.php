<?php

namespace app\admin\model;

use think\Model;

class Mortgage extends Model
{
    // 表名
    protected $name = 'mortgage';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    
    // 追加属性
    protected $append = [
        'mortgage_type_text'
    ];
    

    
    public function getMortgageTypeList()
    {
        return ['new_car' => __('Mortgage_type new_car'),'used_car' => __('Mortgage_type used_car'),'yueda_car' => __('Mortgage_type yueda_car'),'other_car' => __('Mortgage_type other_car'),'south_firm' => __('Mortgage_type south_firm'),'full_car' => __('Mortgage_type full_car'),'full_other' => __('Mortgage_type full_other')];
    }     


    public function getMortgageTypeTextAttr($value, $data)
    {        
        $value = $value ? $value : (isset($data['mortgage_type']) ? $data['mortgage_type'] : '');
        $list = $this->getMortgageTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
